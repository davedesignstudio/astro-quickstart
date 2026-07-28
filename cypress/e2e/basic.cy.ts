describe('empty spec', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  it('displays the resources text', () => {
    cy.get('h1')
    .contains('Quickstart Template');
  })
  it('renders the image', () => {
    cy.get('.logo').should('have.class', 'is-visible')
    cy.get('img')
    .should('be.visible')
    .and(($img) => {
      expect($img[0].naturalWidth).to.be.greaterThan(0);
    })
  })
  it('animates layout elements on scroll', () => {
    cy.get('.animate-on-scroll').should('have.length.greaterThan', 0)
    cy.get('h1.animate-on-scroll').should('have.class', 'is-visible')
    cy.get('.scroll-showcase h2').last().scrollIntoView()
    cy.get('.scroll-showcase h2').last().should('have.class', 'is-visible')
  })
})