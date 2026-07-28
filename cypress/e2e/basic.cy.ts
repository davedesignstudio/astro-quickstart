describe('empty spec', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  it('displays the resources text', () => {
    cy.get('h1')
    .contains('Quickstart Template');
  })
  it('renders the image', () => {
    cy.get('.logo')
    .should('have.class', 'is-visible')
    cy.get('img')
    .should('be.visible')
    .and(($img) => {
      expect($img[0].naturalWidth).to.be.greaterThan(0);
    })
  })
  it('applies scroll slide animations to layout elements', () => {
    cy.get('.animate-on-scroll').should('have.length.at.least', 1)
    cy.get('.animate-on-scroll.is-visible').should('have.length.at.least', 1)
  })
})