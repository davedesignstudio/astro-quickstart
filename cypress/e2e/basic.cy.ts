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

  it('navigates to contact', () => {
    cy.contains('a', 'Start a project').first().click()
    cy.location('pathname').should('eq', '/contact')
    cy.get('h1').contains('Tell us about the work ahead')
  })
})
