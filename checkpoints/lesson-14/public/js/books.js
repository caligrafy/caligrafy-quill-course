// The catalog page: every action goes through the same capabilities as REST and AI agents
import { capabilities, listBooks, listAuthors, createBook, deleteBook, CapabilityError } from './capabilities.js'

const form = document.getElementById('new-book')
const authorSelect = document.getElementById('book-author')
const tabs = document.getElementById('authors')
const list = document.getElementById('books')
const empty = document.getElementById('empty')
const errorBox = document.getElementById('error')
let authorId = ''   // the selected tab: '' for every author
let allowed = new Set()   // the capabilities this visitor may call

// A refusal of a capability has a message per field (CapabilityError): show them under the form
function showError (e) {
  if (e instanceof CapabilityError) {
    errorBox.textContent = Object.keys(e.fields).length ? Object.values(e.fields).join(' ') : e.message
  } else {
    errorBox.textContent = 'Something went wrong. Try again.'
    console.error(e)
  }
}

function tab (label, id) {
  const b = document.createElement('button')
  b.type = 'button'
  b.setAttribute('role', 'tab')
  b.textContent = label
  b.className = id === authorId ? 'active' : ''
  b.setAttribute('aria-selected', String(id === authorId))
  b.addEventListener('click', () => { authorId = id; showAuthors(); showBooks() })
  return b
}

let authors = []
function showAuthors () {
  tabs.replaceChildren(tab('All', ''), ...authors.map((a) => tab(a.name, String(a.id))))
}

function row (book) {
  const li = document.createElement('li')
  li.className = 'cq-item'
  li.innerHTML = '<div class="cq-item-body"><p class="cq-item-title"><a></a></p><p class="cq-item-meta"></p></div><div class="cq-actions"></div>'
  const link = li.querySelector('a')
  link.href = 'books/' + book.id
  link.textContent = book.title
  li.querySelector('.cq-item-meta').textContent = book.author + ', ' + book.published_year

  if (!allowed.has('delete_book')) return li   // only administrators see Delete
  const remove = document.createElement('button')
  remove.type = 'button'
  remove.className = 'btn btn-secondary'
  remove.textContent = 'Delete'
  remove.addEventListener('click', async () => {
    if (!confirm('Delete "' + book.title + '"?')) return
    try {
      await deleteBook({ id: book.id })
      await showBooks()
    } catch (e) {
      showError(e)
    }
  })
  li.querySelector('.cq-actions').append(remove)
  return li
}

async function showBooks () {
  const books = await listBooks(authorId ? { author_id: authorId } : {})
  list.replaceChildren(...books.map(row))
  empty.hidden = books.length > 0
}

form.addEventListener('submit', async (event) => {
  event.preventDefault()
  errorBox.textContent = ''
  try {
    await createBook(Object.fromEntries(new FormData(form)))
    form.reset()
    form.elements.title.focus()
    await showBooks()
  } catch (e) {
    showError(e)
  }
})

// What may this visitor do? GET /capabilities lists only the capabilities they may call
allowed = new Set((await capabilities()).map((c) => c.name))
form.hidden = !allowed.has('create_book')
document.getElementById('sign-in').hidden = allowed.has('create_book')

authors = await listAuthors()
authorSelect.replaceChildren(...authors.map((a) => new Option(a.name, a.id)))
showAuthors()
await showBooks()
