(() => {
  const sidebar = document.getElementById('app-sidebar');
  const toggle = document.getElementById('nav-toggle');
  if (!sidebar || !toggle) return;
  const overlay = document.getElementById('nav-overlay');
  const close = document.getElementById('nav-close');
  const account = document.querySelector('.account-menu');
  const mobile = window.matchMedia('(max-width: 991.98px)');
  document.documentElement.classList.add('js');
  const setOpen = (open, restoreFocus = false) => {
    document.body.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    sidebar.inert = mobile.matches && !open;
    if (open && account) account.open = false;
    if (open) close.focus();
    else if (restoreFocus) toggle.focus();
  };
  toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
  close.addEventListener('click', () => setOpen(false, true));
  overlay.addEventListener('click', () => setOpen(false, true));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && account?.open) {
      account.open = false;
      account.querySelector('summary').focus();
    }
    if (!mobile.matches || !document.body.classList.contains('nav-open')) return;
    if (event.key === 'Escape') { event.preventDefault(); setOpen(false, true); }
    if (event.key !== 'Tab') return;
    const items = [...sidebar.querySelectorAll('a[href], button:not([disabled])')].filter((item) => item.getClientRects().length);
    const first = items[0], last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  document.addEventListener('click', (event) => {
    if (account?.open && !account.contains(event.target)) account.open = false;
  });
  mobile.addEventListener('change', () => setOpen(false));
  setOpen(false);
})();
