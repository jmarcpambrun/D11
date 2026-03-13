CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Counter module collects request related information whenever website is
accessed and store it in the database. All the information is then
exposed to users via a block. The main statistics provided by the module
are:
  - Display Site Counter
  - Display Clients IP
  - Display Unique Visitor based on their IP Address
  - You can set initial value for Counter
  - Display node count
  - Display user count

* For a full description of the module, visit the project page:
  https://www.drupal.org/project/counter

* To submit bug reports and feature suggestions, or to track
  changes: https://www.drupal.org/project/issues/counter

REQUIREMENTS
------------

This module doesn't require any module outside of Drupal core.

CONFIGURATION
-------------

1. You can configure all the settings related to counter from 
('/admin/config/counter').

2. After that you can place the "Counter" block from the block layout and
check all the data by collected by this module.

3. You can also used data collected by Counter in a view by creating a new
view of type 'Counter'.

4. Make sure cron job provided by the module is configured to show the latest
data. In case, it has been disabled make sure you are invalidating
'counter_data_refresh' through any other custom logic.

MAINTAINERS
-----------

 * Gaurav Kapoor https://www.drupal.org/u/gauravkapoor

 Supporting organization:

 * Axelerant - https://www.drupal.org/axelerant

 * Glyanec - https://www.drupal.org/glyanec


