# QNAAPI Connect

A WordPress plugin that connects a site to a [QNAAPI](https://qnaapi.com) account: create polls and quizzes from wp-admin, embed polls, quizzes, and forms/surveys anywhere with a shortcode or Gutenberg block, define audience traits, and browse respondent profiles.

See [`readme.txt`](readme.txt) for the WordPress.org-facing description, FAQ, and changelog — that file is the source of truth once this is submitted to the plugin directory.

## Local development

This plugin has no build step (no npm/composer dependencies) — it's plain PHP and vanilla JS, so you can just symlink it into a local WordPress install:

```bash
ln -s "$(pwd)" /path/to/wordpress/wp-content/plugins/qnaapi-connect
```

Then activate it from **Plugins** in wp-admin, and add an API key under **QNAAPI → Settings** (see [qnaapi.com/docs/api-keys](https://qnaapi.com/docs/api-keys)).

## Structure

- `qnaapi-connect.php` — plugin bootstrap.
- `includes/` — one class per subsystem (API client, settings, admin screens, shortcodes, blocks, dashboard widget, asset enqueuing).
- `blocks/{poll,quiz,form}/` — Gutenberg block definitions; each block's editor UI renders a live preview via `ServerSideRender`, sharing its output with the matching shortcode.
- `assets/` — frontend and admin CSS/JS.

## Publishing to WordPress.org

Not yet submitted. Before submitting: update `readme.txt`'s `Contributors` field to the real WordPress.org username, add real screenshots, and run the plugin through the [Plugin Check](https://wordpress.org/plugins/plugin-check/) tool.
