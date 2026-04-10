Views TimelineJS
================
This module adds a new style plugin for Views which renders result rows as
TimelineJS slides and eras.  The 8.x-3.x and 4.x branches were created to work
with the TimelineJS3 version of the library.  For more information about
TimelineJS visit the
[official website](https://timeline.knightlab.com/index.html) or the [GitHub
repository](https://timeline.knightlab.com/index.html).

Installation
------------
Download the module using Composer with the command ```composer require
drupal/views_timelinejs``` and enable it.  By default, there are no library
files to download because they are served from the NU Knight Lab CDN.  New
installations will be configured to use the last version with which the module
was tested.  You can change this setting at
```admin/config/development/views_timelinejs```.  Other options include using
the latest version of TimelineJS from the CDN or to serve the library files
locally from your own server.

Please note that using the latest version of the library is not recommended.
Recently the library maintainers have shown that they may introduce changes that
break backward compatibility for the library's API.  If this happens again in
the future, then it is possible that some functionality may cease to work with
no warning.  The latest version option is available in case you need it for bug
fixes for feature additions, but use it at your own risk.

If you want to serve the library files from your own site instead of the CDN,
then you need to download them.  ZIP files of the compiled library are
available.  You may download a specific version from
```https://cdn.knightlab.com/libs/timeline3/VERSION-NUMBER/timeline3.zip```, for
example:
* https://cdn.knightlab.com/libs/timeline3/3.8.18/timeline3.zip
* https://cdn.knightlab.com/libs/timeline3/latest/timeline3.zip

You **must** put the TimelineJS library in the /libraries directory inside your
Drupal installation.  Alternate library locations such as those checked by the
Libraries API module will not work.  The timeline.js and timeline.css files
are required to use TimelineJS.  The library also includes several font
library CSS files that must be downloaded if you want to use them.  In the end,
you need to have the following files in these directories:

1. ```/libraries/timeline3/js/timeline.js```
2. ```/libraries/timeline3/css/timeline.css```
3. ```/libraries/timeline3/css/fonts/font.FONT-NAME.css``` (optional)

Other methods of downloading the library will not be supported.  Previously
these instructions recommended cloning the library's repository from GitHub, but
the repo no longer contains the compiled files.

Upgrading
---------
If you are upgrading this module from version 7.x-1.x then make sure you test
your view and reconfigure it before deploying to a production environment!  Much
of the plugin's functionality was changed in the upgrade to TimelineJS3.
Version 3 of the library offers several nice enhancements over version 2.  The
plugin has received a lot of updates in order to take full advantage of the new
library.  Some settings have been changed or removed.  New settings have been
added.  The fact that the Date field setting has been split into separate Start
date and End date field settings means that all existing views that were built
with version 7.x-1.x will need to be configured differently in Drupal 9.

Configuring the Plugin
----------------------
Configuring this plugin can be a challenge.  That's because TimelineJS has a
large number of data fields and settings.  This is especially true for setting
up media fields.  Extensive documentation has been written to help guide you in
this process.  Please don't try to do it alone!

The configuration documentation is maintained on Drupal.org.  The available
documentation includes:

* [How to install and use Views TimelineJS](
https://www.drupal.org/docs/8/modules/views-timelinejs/how-to-install-and-use-views-timelinejs
)
* [The types of field data that TimelineJS expects](
https://www.drupal.org/docs/8/modules/views-timelinejs/configuring-the-plugin)
* [Date and time formats that can be parsed by the plugin](
https://www.drupal.org/docs/8/modules/views-timelinejs/date-and-time-formats)
* [Configuring your site and view to use different types of media](
https://www.drupal.org/docs/8/modules/views-timelinejs/media-field-configuration
)

Maintainers
-----------
* Juha Niemi (juhaniemi)
* Olli Erinko (operinko)
* Jon Peck (fluxsauce)
* WorldFallz
* David Cameron (dcam)
