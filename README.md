# Payannameh Plus

Run locally: `npm install` then `npm run dev` (open http://localhost:4321).
Build: `npm run build` (output in `dist/`).

## Before deploying
1. `astro.config.mjs`: set `site` to the real domain. Also update the Sitemap line in `public/robots.txt`.
2. `public/admin/config.yml`: set `repo` to `YOUR-GITHUB-USERNAME/payannameh-plus`.
3. Push to GitHub, then connect the repo to Netlify (build: `npm run build`, publish: `dist`).
4. Netlify: Site settings > Access control > OAuth > install GitHub provider. Then the owner logs in at `/admin` with their GitHub account.

## Edit contact info or services
`src/data/services.ts`
