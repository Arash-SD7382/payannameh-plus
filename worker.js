// Cloudflare Worker entry: GitHub login for the Decap CMS panel (/admin)
// + serving the static Astro site (dist/) through the ASSETS binding.
//
// Required secrets (set in Cloudflare dashboard or with `wrangler secret put`):
//   GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    if (url.pathname === '/api/auth') return startLogin(url, env);
    if (url.pathname === '/api/callback') return finishLogin(request, url, env);
    const res = await env.ASSETS.fetch(request);
    if (res.status !== 404) return res;
    return notFound(request, env, url, res);
  },
};

// Unknown address: show the site's own 404 page (Persian, or English under /en/) with a real 404 status.
async function notFound(request, env, url, original) {
  if (request.method !== 'GET' && request.method !== 'HEAD') return original;
  const english = url.pathname === '/en' || url.pathname.startsWith('/en/');
  // canonical asset addresses (html_handling = auto-trailing-slash): /404 and /en/404/
  let page = await env.ASSETS.fetch(new Request(new URL(english ? '/en/404/' : '/404', url.origin)));
  // follow one redirect in case the asset layer normalizes the address
  if (page.status >= 300 && page.status < 400 && page.headers.get('Location')) {
    page = await env.ASSETS.fetch(new Request(new URL(page.headers.get('Location'), url.origin)));
  }
  if (!page.ok) return original;
  return new Response(request.method === 'HEAD' ? null : page.body, {
    status: 404,
    headers: { 'Content-Type': 'text/html;charset=UTF-8', 'Cache-Control': 'no-store' },
  });
}

// Step 1: send the user to GitHub to approve the login.
function startLogin(url, env) {
  if (!env.GITHUB_CLIENT_ID || !env.GITHUB_CLIENT_SECRET) {
    return new Response('Missing GITHUB_CLIENT_ID / GITHUB_CLIENT_SECRET secrets on this Worker.', { status: 500 });
  }
  const state = crypto.randomUUID();
  const params = new URLSearchParams({
    client_id: env.GITHUB_CLIENT_ID,
    redirect_uri: `${url.origin}/api/callback`,
    scope: 'repo,user',
    state,
  });
  return new Response(null, {
    status: 302,
    headers: {
      Location: `https://github.com/login/oauth/authorize?${params}`,
      'Set-Cookie': `oauth_state=${state}; HttpOnly; Secure; Path=/; SameSite=Lax; Max-Age=600`,
    },
  });
}

// Step 2: GitHub sends the user back here; trade the code for a token
// and hand it to the Decap CMS window that opened this popup.
async function finishLogin(request, url, env) {
  const code = url.searchParams.get('code');
  const state = url.searchParams.get('state');
  const saved = ((request.headers.get('Cookie') || '').match(/oauth_state=([^;]+)/) || [])[1];
  if (!code || !state || state !== saved) {
    return new Response('Invalid login request. Please try again.', { status: 400 });
  }

  const res = await fetch('https://github.com/login/oauth/access_token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      client_id: env.GITHUB_CLIENT_ID,
      client_secret: env.GITHUB_CLIENT_SECRET,
      code,
    }),
  });
  const data = await res.json();
  const message = data.access_token
    ? 'authorization:github:success:' + JSON.stringify({ token: data.access_token, provider: 'github' })
    : 'authorization:github:error:' + JSON.stringify({ error: data.error || 'login_failed' });

  const html = `<!doctype html><html><body><script>
(function () {
  var msg = ${JSON.stringify(message)};
  function receive(e) { window.opener.postMessage(msg, e.origin); window.removeEventListener('message', receive, false); }
  window.addEventListener('message', receive, false);
  window.opener.postMessage('authorizing:github', '*');
})();
</script></body></html>`;
  return new Response(html, {
    headers: {
      'Content-Type': 'text/html;charset=UTF-8',
      'Set-Cookie': 'oauth_state=; HttpOnly; Secure; Path=/; Max-Age=0',
    },
  });
}
