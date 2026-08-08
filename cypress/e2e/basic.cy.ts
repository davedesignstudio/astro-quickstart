describe('D. Philhower Studio site', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  it('displays the studio brand in the hero', () => {
    cy.get('h1').contains('D. Philhower')
    cy.get('h1').contains('Studio')
  })

  it('renders the hero image', () => {
    cy.get('.hero-media img')
      .should('be.visible')
      .and(($img) => {
        expect($img[0].naturalWidth).to.be.greaterThan(0)
      })
  })

  it('shows the type wall assemblage', () => {
    cy.get('.type-wall').should('exist')
    cy.get('.type-assemblage .type-cell').should('have.length.greaterThan', 8)
  })

  it('navigates to influences research', () => {
    cy.contains('a', 'See the influences').click()
    cy.location('pathname').should('eq', '/influences')
    cy.get('h1').contains('Research behind the studio language')
  })
})
