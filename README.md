# Site Factory — Content & Site Engine 1.0.0

Deterministic WordPress Site/Content Factory without runtime AI/API dependency.

## Included

- Product source-of-truth CPT with UA/RU fields.
- Region and City entities.
- Ukraine geography seed for 22 oblasts (excluding Crimea, Donetsk and Luhansk oblasts) with five cities each; entities start as admin data and are **not** auto-published.
- Content Matrix: CREATE / MERGE / SKIP.
- Product + City usefulness gate.
- Deterministic intent classification for product queries.
- Queue backed by a dedicated DB table and WP-Cron.
- Deterministic Structural + Editorial Variation with 30 stored editorial parameters.
- Duplicate-safe generation using signatures and similarity QA.
- SEO: title, description, canonical, robots, hreflang, Open Graph (when no Yoast/Rank Math), JSON-LD, sitemap exclusion for noindex pages.
- Five frontend themes using one shared design system.
- `[sf_home]` shortcode and generated standalone page renderer.
- No destructive uninstall: generated content is preserved.

## Install

1. Upload `site-factory-content-engine.zip` in WordPress → Plugins.
2. Activate.
3. Open **Site Factory → Dashboard**.
4. Click **Загрузить базовую географию Украины** to seed region/city entities.
5. Create products in **Site Factory → Товары** (or the WP admin list under Site Factory).
6. Fill UA first; fill RU fields when a real Russian version is available. The generator does not auto-translate.
7. Open **Content Matrix**, inspect CREATE/MERGE/SKIP, then queue CREATE.
8. Let WP-Cron process the queue. The plugin generates pages sequentially and avoids duplicates.
9. Open **Дизайн и настройки** to choose one of five themes.
10. Create the Home page with the provided action or add `[sf_home]` to an existing page.

## Important architecture choices

- SKIP means no page is created. NOINDEX is reserved for technical/indexing cases.
- Product + City is not generated from a blind Cartesian product.
- Generated pages are normal WordPress Pages with hidden Site Factory metadata and language-root parents (`SF UA` / `SF RU`). Product/City/Region are source entities, not duplicate public pages.
- WooCommerce is not required. CTA is URL-based and can later be replaced by an adapter.
- No runtime AI call is made by this plugin.
