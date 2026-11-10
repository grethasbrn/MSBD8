const BASE = '/api';

async function api(path, { method = 'GET', body, form } = {}) {
  const headers = { Accept: 'application/json' };
  const token = localStorage.getItem('token');
  if (token) headers.Authorization = 'Bearer ' + token;

  let payload;
  if (form) {
    payload = form;
  } else if (body) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  const res = await fetch(BASE + path, { method, headers, body: payload });
  const data = await res.json().catch(() => ({}));

  if (res.status === 401 && !path.startsWith('/auth/login')) {
    localStorage.clear();
    location.href = '/ui/login.html';
    return;
  }
  if (!res.ok) {
    const err = new Error(data.message || 'Terjadi kesalahan');
    err.errors = data.errors;
    throw err;
  }
  return data;
}

const rupiah = n => 'Rp' + Number(n).toLocaleString('id-ID');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
