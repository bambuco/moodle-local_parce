Marked.js - A markdown parser and compiler
===========================================

This folder contains the Marked.js library, a markdown parser and compiler built for speed.

Version: 11.1.1
License: MIT (Compatible with GPLv3)
Original Source: https://github.com/markedjs/marked
Original Author: Christopher Jeffrey and contributors

How this library was installed:
==============================

1. Downloaded the latest stable release from npm:
   URL: https://registry.npmjs.org/marked/-/marked-11.1.1.tgz

2. Extracted the tarball and obtained the minified version:
   File: marked-11.1.1/marked.min.js

3. Placed the minified file in this directory:
   Path: lib/marked/marked.min.js

How to update this library:
===========================

1. Check for the latest version at:
   https://github.com/markedjs/marked/releases
   or
   https://www.npmjs.com/package/marked

2. Download the latest stable release:
   npm pack marked@<VERSION>
   (Or download directly from: https://registry.npmjs.org/marked/-/marked-<VERSION>.tgz)

3. Extract the tarball:
   tar -xzf marked-<VERSION>.tgz

4. Copy the minified version:
   cp package/marked.min.js lib/marked/marked.min.js

5. Update the version number in:
   - thirdpartylibs.xml (the <version> tag)
   - This file (readme_moodle.txt)
   - upgrade.txt or CHANGES.md

6. Run grunt to regenerate ignored files:
   grunt ignorefiles

7. Commit and push the changes to your repository.

Library Details:
================

Marked is a high-performance markdown parser that is:
- Fast and lightweight
- Supports GitHub Flavored Markdown (GFM)
- Extensible with custom renderers
- Zero external dependencies

The library is loaded globally in Moodle via the output hook in:
classes/hooks/output.php

It is accessed in AMD modules as: window.marked

Usage in Moodle:
===============

The library is loaded in the output hook (see classes/hooks/output.php):
$PAGE->requires->js(new moodle_url('/local/parce/lib/marked/marked.min.js'), true);

Then it's available globally and can be used in any AMD module via:
window.marked.parse(markdown, options);
window.marked.parseInline(markdown, options);

For more information about Marked, see:
https://marked.js.org/
