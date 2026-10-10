// Finishes the GitHub login and hands the token back to the Decap CMS window.
export async function onRequest({ request, env }) {
  const url = new URL(request.url);
  const code = url.searchParams.get('code');
  const state = url.searchParams.get('state');
  const saved = ((request.headers.get('Cookie') || '').match(/oauth_state=([^;]+)/) || [])[1];
  if (!code || !state || state !== saved) return new Response('Invalid login request. Please try again.', { status: 400 });

  const res = await fetch('https://github.com/login/oauth/access_token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ client_id: env.GITHUB_CLIENT_ID, client_secret: env.GITHUB_CLIENT_SECRET, code }),
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
