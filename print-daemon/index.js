#!/usr/bin/env node

/**
 * Restaurant Print Daemon
 *
 * Polls the WordPress REST API for pending kitchen tickets and prints them
 * to a local ESC/POS thermal printer (network, USB, or file for testing).
 *
 * Environment variables:
 *   ROP_API_URL    - WordPress site URL (e.g. http://localhost:8080)
 *   ROP_API_KEY    - Daemon API key from WP admin (WooCommerce > Kitchen Print)
 *   ROP_PRINTER    - Printer target:
 *                      network://192.168.1.100:9100  (network ESC/POS)
 *                      file:///tmp/kitchen-tickets.txt (file output for testing)
 *   ROP_POLL_MS    - Poll interval in ms (default: 3000)
 */

require('dotenv').config();

const fs = require('fs');
const net = require('net');
const http = require('http');
const https = require('https');

const config = {
  apiUrl: process.env.ROP_API_URL || 'http://localhost:8080',
  apiKey: process.env.ROP_API_KEY || '',
  printer: process.env.ROP_PRINTER || 'file:///tmp/kitchen-tickets.txt',
  pollMs: parseInt(process.env.ROP_POLL_MS || '3000', 10),
};

function log(level, message) {
  const ts = new Date().toISOString();
  console.log(`[${ts}] [${level}] ${message}`);
}

function apiRequest(method, path, body = null) {
  return new Promise((resolve, reject) => {
    const url = new URL(path, config.apiUrl);
    const lib = url.protocol === 'https:' ? https : http;

    const options = {
      hostname: url.hostname,
      port: url.port || (url.protocol === 'https:' ? 443 : 80),
      path: url.pathname + url.search,
      method,
      headers: {
        'X-ROP-API-Key': config.apiKey,
        'Content-Type': 'application/json',
      },
    };

    const req = lib.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => (data += chunk));
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, body: JSON.parse(data) });
        } catch {
          resolve({ status: res.statusCode, body: data });
        }
      });
    });

    req.on('error', reject);
    if (body) req.write(JSON.stringify(body));
    req.end();
  });
}

function printToNetwork(host, port, data) {
  return new Promise((resolve, reject) => {
    const socket = new net.Socket();
    socket.setTimeout(10000);

    socket.connect(port, host, () => {
      socket.write(data, (err) => {
        if (err) return reject(err);
        socket.end();
        resolve();
      });
    });

    socket.on('error', reject);
    socket.on('timeout', () => {
      socket.destroy();
      reject(new Error('Printer connection timed out'));
    });
  });
}

function printToFile(filePath, data) {
  return new Promise((resolve, reject) => {
    const text = Buffer.isBuffer(data) ? data.toString('utf8') : data;
    fs.appendFile(filePath, '\n--- TICKET ---\n' + text + '\n', (err) => {
      if (err) reject(err);
      else resolve();
    });
  });
}

async function sendToPrinter(job) {
  const escpos = job.escpos ? Buffer.from(job.escpos, 'base64') : Buffer.from(job.text, 'utf8');
  const target = config.printer;

  if (target.startsWith('network://')) {
    const parts = target.replace('network://', '').split(':');
    const host = parts[0];
    const port = parseInt(parts[1] || '9100', 10);
    await printToNetwork(host, port, escpos);
  } else if (target.startsWith('file://')) {
    const filePath = target.replace('file://', '');
    const text = job.text || escpos.toString('utf8');
    await printToFile(filePath, text);
  } else {
    throw new Error(`Unknown printer target: ${target}`);
  }
}

async function processQueue() {
  try {
    const res = await apiRequest('GET', '/wp-json/restaurant-print/v1/queue?limit=5');

    if (res.status === 401) {
      log('ERROR', 'API key rejected. Check ROP_API_KEY matches WordPress settings.');
      return;
    }

    if (res.status !== 200) {
      log('WARN', `API returned status ${res.status}`);
      return;
    }

    const jobs = res.body.jobs || [];
    if (jobs.length === 0) return;

    for (const job of jobs) {
      log('INFO', `Printing ticket for order #${job.order_number} (job ${job.id})`);

      try {
        await sendToPrinter(job);
        await apiRequest('POST', `/wp-json/restaurant-print/v1/queue/${job.id}/complete`);
        log('INFO', `Ticket printed successfully (job ${job.id})`);
      } catch (err) {
        log('ERROR', `Print failed for job ${job.id}: ${err.message}`);
        await apiRequest('POST', `/wp-json/restaurant-print/v1/queue/${job.id}/fail`, {
          message: err.message,
        });
      }
    }
  } catch (err) {
    log('ERROR', `Queue poll failed: ${err.message}`);
  }
}

async function testConnection() {
  log('INFO', 'Testing API connection...');
  const res = await apiRequest('GET', '/wp-json/restaurant-print/v1/test');
  if (res.status === 200) {
    log('INFO', 'API connection OK');
  } else {
    log('ERROR', `API test failed: ${res.status}`);
    process.exit(1);
  }

  log('INFO', 'Sending test print...');
  const testTicket = {
    text: '=== TEST TICKET ===\nRestaurant Print Daemon\nConnection successful!\n===================',
    escpos: '',
  };
  await sendToPrinter(testTicket);
  log('INFO', 'Test print sent. Check your printer or output file.');
}

async function main() {
  if (!config.apiKey) {
    log('ERROR', 'ROP_API_KEY is required. Set it in .env or environment.');
    process.exit(1);
  }

  log('INFO', `Restaurant Print Daemon starting`);
  log('INFO', `API: ${config.apiUrl}`);
  log('INFO', `Printer: ${config.printer}`);
  log('INFO', `Poll interval: ${config.pollMs}ms`);

  if (process.argv.includes('--test')) {
    await testConnection();
    process.exit(0);
  }

  await processQueue();
  setInterval(processQueue, config.pollMs);
}

main().catch((err) => {
  log('FATAL', err.message);
  process.exit(1);
});
