.PHONY: up down setup logs restart

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

setup:
	./scripts/setup.sh

logs:
	docker compose logs -f wordpress
