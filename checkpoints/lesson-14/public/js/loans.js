// The loans page: lend_book, return_book and list_loans, the same capabilities as REST and AI agents
import { capabilities, listBooks, listLoans, lendBook, returnBook, CapabilityError } from './capabilities.js'

const form = document.getElementById('lend')
const list = document.getElementById('loans')
const empty = document.getElementById('empty')
const errorBox = document.getElementById('error')

function showError (e) {
  if (e instanceof CapabilityError) {
    errorBox.textContent = Object.keys(e.fields).length ? Object.values(e.fields).join(' ') : e.message
  } else {
    errorBox.textContent = 'Something went wrong. Try again.'
    console.error(e)
  }
}

function row (loan) {
  const li = document.createElement('li')
  li.className = 'cq-item'
  li.innerHTML = '<div class="cq-item-body"><p class="cq-item-title"></p><p class="cq-item-meta"></p></div><div class="cq-actions"></div>'
  li.querySelector('.cq-item-title').textContent = loan.title
  li.querySelector('.cq-item-meta').textContent = loan.borrower + ', due ' + loan.due_on + (loan.overdue ? ' (late)' : '')

  const back = document.createElement('button')
  back.type = 'button'
  back.className = 'btn btn-secondary'
  back.textContent = 'Returned'
  back.addEventListener('click', async () => {
    try {
      await returnBook({ book_id: loan.book_id })
      await showLoans()
    } catch (e) {
      showError(e)
    }
  })
  li.querySelector('.cq-actions').append(back)
  return li
}

async function showLoans () {
  const loans = await listLoans()
  list.replaceChildren(...loans.map(row))
  empty.hidden = loans.length > 0
}

form.addEventListener('submit', async (event) => {
  event.preventDefault()
  errorBox.textContent = ''
  const input = Object.fromEntries(new FormData(form))
  if (input.days === '') delete input.days   // empty: 14 days
  try {
    await lendBook(input)
    form.reset()
    await showLoans()
  } catch (e) {
    showError(e)   // for example "This book is already lent to Sam"
  }
})

// Visitors who may not see the loans get the sign-in button instead
const allowed = new Set((await capabilities()).map((c) => c.name))
if (allowed.has('list_loans')) {
  form.hidden = !allowed.has('lend_book')
  const books = await listBooks()
  document.getElementById('loan-book').replaceChildren(...books.map((b) => new Option(b.title, b.id)))
  await showLoans()
} else {
  document.getElementById('sign-in').hidden = false
}
