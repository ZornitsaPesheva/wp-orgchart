=== OrgChart ===
Contributors: balkanapp
Tags: org chart, organizational chart, hierarchy, employee chart, company structure
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easily add an interactive and editable organizational chart to any page or post using the [orgchart] shortcode.

== Description ==

**OrgChart** is a simple and powerful plugin that allows you to embed an interactive organizational chart into your WordPress site using the [Balkan OrgChart.js](https://balkan.app/OrgChartJS) library.

Features include:
- Add, edit, and remove team members from the frontend.
- Live AJAX updates — no page reloads.
- Upload images to the WordPress Media Library directly from the chart interface.
- Data is stored in the WordPress database (no external service required).
- Mobile and desktop responsive layout.

Use the `[orgchart]` shortcode in any post or page to display the chart.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/orgchart/` or install the plugin through the WordPress admin panel.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Add the shortcode `[orgchart]` to any page or post where you want to display the chart.
4. Customize your chart by clicking on the nodes to add or edit members.

== Usage ==

Use the shortcode below in any page or post:

`[orgchart]`

The chart will be rendered in that location.

You can interact with the chart as follows:
- Click on the node menu to add, edit, or remove it.
- Data is saved automatically via AJAX.

== Frequently Asked Questions ==

= Where is the data stored? =
The chart data is saved in the WordPress options table under the key `orgchart_data`.

= Who can edit the chart? =
By default, anyone visiting the page can make changes. You can restrict access by editing the plugin and removing support for non-logged-in users (e.g., by removing `wp_ajax_nopriv_*` hooks).

= Can I export or backup the data? =
Since the chart data is stored as a JSON string in the options table, you can back it up using any WordPress backup tool or by manually exporting the `orgchart_data` option.

== Screenshots ==

1. Example of a loaded organizational chart.
2. Edit form for adding or modifying a team member.
3. Node menu for managing nodes.

== Changelog ==

= 1.0.0 =
* Initial release with full support for viewing, adding, editing, deleting, and image uploading via AJAX.

== Upgrade Notice ==

= 1.0.0 =
First release. No upgrade steps necessary.

== License ==

This plugin is licensed under the GPLv2 or later.
OrgChart JS by BALKAN App is used under its own license.
