=== Nova Matric Results Search ===
Contributors: Nova News
Tags: matric, results, search, csv, south africa
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later

A plugin to search matric examination results from imported CSV files.

== Description ==

This plugin allows users to search for matric results using their examination number.
It supports importing results from multiple CSV files (e.g. one per province) into a custom database table for fast searching.

Features:
- Search widget shortcode.
- Results display shortcode.
- Import data from CSV files.
- Displays Achievement Type and Outstanding Subjects.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/nova-matric-results` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the **Matric Results** menu in the admin dashboard to manage data.

== Data Import ==

1. Prepare your CSV files. Expected columns: `Province, EMIS, Centre Name, Exam Number, Type of Achievement, Subj Code 1, Subj Name 1, ...`
2. Upload your CSV files to the `wp-content/plugins/nova-matric-results/csv/` directory on your server (via FTP/SFTP).
3. Go to **Matric Results** in the WordPress Admin.
4. Click **Import Data from CSV Folder**.

== Usage ==

Place the following shortcodes on your pages:

**Search Form:**
`[matric_search_form]`

**Results Display:**
`[matric_results]`

You can place them on the same page, or separate pages (ensure the form submits to the page with the results shortcode).

== Screenshots ==

1. Search Form
2. Results Card

== Changelog ==

= 1.0.0 =
* Initial release.
