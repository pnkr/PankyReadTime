
# PankyReadTime (Joomla 5.x / 6 Content Plugin)

**Version: 3.0.1**

Fixed: Route to update channel

**Version: 3.0.0**

This plugin displays the average reading time for the current article and a scroll progress bar on Joomla 5.x sites. Output is shown only on single article pages (`com_content.article`).

## Features
- Configurable reading speed (words per minute)
- Option to show seconds in the estimate
- Badge position: before content, after title, or after content
- Badge format: verbose (e.g. "3 minutes, 20 seconds") or compact (e.g. "3m 20s")
- Optional "Finish by" time (shows when the article will be finished based on server time)
- Customizable progress bar (color, height, top/bottom, optional percent label)
- Fully localized (English/Greek)

## Usage
1. Install via Joomla Extensions → Install (upload the zip or copy to `plugins/content/pankyreadingtime` and use Discover/Install).
2. Enable the plugin in the Joomla admin.
3. Configure options in the plugin settings:
	- Reading speed
	- Show seconds
	- Badge position/format
	- Show finish time
	- Progress bar options
4. View any article page to see the badge and progress bar.

## Example Output
![image](https://user-images.githubusercontent.com/4727788/229353679-9276f560-3024-422d-919d-adce7d6485d6.png)

## Developer Notes
- Only runs on site frontend and single article context
- No build step or Composer dependencies
- Language keys in `language/en-GB` and `language/el-GR`
- Manifest and code version must match for releases

## Changelog
**3.0.0**
- Restrict output to single article view
- Remove unused visibility toggles
- Add badge position, format, finish time, and progress bar options
- Language file cleanup and new keys
