=== QNAAPI Connect ===
Contributors: qnaapi
Tags: polls, quizzes, forms, surveys, api
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and embed polls, quizzes, and forms/surveys powered by QNAAPI — right from wp-admin, no coding required.

== Description ==

QNAAPI Connect links your WordPress site to your [QNAAPI](https://qnaapi.com) account so you can build polls and quizzes without leaving wp-admin, then drop them into any post, page, or template with a shortcode or block.

**Features**

* Create polls, quizzes, and forms/surveys on the fly from a dedicated wp-admin screen — no API calls to write yourself.
* `[qnaapi_poll identifier="..."]`, `[qnaapi_quiz identifier="..."]`, and `[qnaapi_form identifier="..."]` shortcodes to embed any poll, quiz, or form/survey on your QNAAPI account.
* Matching Gutenberg blocks (QNAAPI Poll, QNAAPI Quiz, QNAAPI Form) with a live editor preview.
* Define audience traits (**QNAAPI → Traits**) — the attributes your polls, quizzes, and forms write to a respondent's profile.
* Browse respondent profiles and their computed traits (**QNAAPI → Profiles**) without leaving wp-admin.
* A wp-admin dashboard widget showing live poll/quiz/form counts, total votes cast, traits defined, and respondent profiles.
* Voting, quiz attempts, and form submissions are handled entirely client-side against QNAAPI's public endpoints — your API key never reaches visitors' browsers.

**Requirements**

You need a free or paid [QNAAPI](https://qnaapi.com) account and an API key (Settings → QNAAPI Connect → API Key). See the [QNAAPI API docs](https://qnaapi.com/docs) for details on how polls, quizzes, and forms work.

This plugin is an independent client for the QNAAPI service and requires an active internet connection to qnaapi.com to function.

== Installation ==

1. Install and activate the plugin.
2. Go to **QNAAPI → Settings** and paste in an API key from your QNAAPI dashboard's "API health & keys" page.
3. Create a poll, quiz, or form from **QNAAPI → Polls**, **QNAAPI → Quizzes**, or **QNAAPI → Forms**, or use the shortcode/block for a resource you already created on qnaapi.com.
4. Copy the shortcode shown next to the resource, or insert the matching block, into any post or page.

== Frequently Asked Questions ==

= Does this work without a QNAAPI account? =

No — you need an API key from a QNAAPI account to create or manage resources. Casting a vote, taking a quiz, or submitting a form doesn't require an account; that part is public and handled by your site's visitors.

= Does voting/submitting go through my WordPress server? =

No. The plugin's PHP only fetches the poll/quiz/form's definition (using your API key) to render it. Casting a vote, submitting a quiz attempt, or submitting a form happens directly from the visitor's browser to QNAAPI's public API — your API key is never sent to or exposed in the visitor's browser.

= Can I map a poll option or form field to a trait from wp-admin? =

Not yet — you can define the trait itself from **QNAAPI → Traits**, but mapping a specific option/choice/field (or a whole poll/quiz/form) to it is done from the QNAAPI dashboard. The **QNAAPI → Profiles** screen shows the resulting computed traits for each respondent.

== Screenshots ==

1. The Polls admin screen — create a poll and copy its shortcode.
2. A poll embedded on the front end.
3. The QNAAPI Poll block in the editor.

== Changelog ==

= 0.3.0 =
* Add a Forms admin screen (**QNAAPI → Forms**) to create, list, and delete forms/surveys from wp-admin — matching the existing Polls/Quizzes screens.

= 0.2.0 =
* Add a Traits admin screen (**QNAAPI → Traits**) to create, list, and archive trait definitions.
* Add a Profiles admin screen (**QNAAPI → Profiles**) to browse respondents and the traits computed for them.
* Add traits-defined and respondent-profile counts to the dashboard widget.

= 0.1.0 =
* Initial release: settings, poll/quiz creation, shortcodes, blocks, and a dashboard widget.

== Upgrade Notice ==

= 0.3.0 =
Adds a Forms admin screen — no breaking changes.

= 0.2.0 =
Adds trait and profile management screens — no breaking changes.

= 0.1.0 =
Initial release.
