About
=====
This module looks up the country associated with a visitor's IPv4 address. The
module makes this determination and stores the result as an ISO 3166
2-character country code in the Drupal $user object, but otherwise has no
effect on the operation of your site. The country is stored for authenticated
users at login; the lookup service also supports anonymous visitors.
An API is also provided so that you can lookup IP-Country information in your
own code.

Installation
============
Check requirements:  Drupal 10 (10.3 or later) or Drupal 11

Installation: composer require drupal/ip2country

In your web browser, navigate to admin/modules and enable the following
module: IP-based determination of Country

NOTE!  When ip2country is installed, it downloads a large amount of
data off the Internet to build a table in your Drupal database.  This
process takes time, so please be patient and WAIT until
the page loads!  You can verify a correct install by looking into your
Drupal database for the ip2country table and verifying that it is full
of data.

This module defines an "administer ip2country" permission, which must be
explicitly enabled for the administration user at admin/people/permissions.

You must now enter values in the administration menus.  Defaults are chosen
reasonably, but you should examine them and set them as you wish.

Go to admin/config/people/ip2country to review and change settings for the
IP-based determination of Country module.  You can read about the Debug
preferences in the "Troubleshooting" section below.

Finally, cron needs to be running for automatic database updates.  If you
haven't set up cron for your Drupal site, refer to
https://www.drupal.org/docs/administering-a-drupal-site/cron-automated-tasks
for instructions.

Everything should now work.  If it doesn't, read the rest of this document
(which you really should have done first, anyway!).  If you still have
problems see the "Troubleshooting" section below.



Features
========
This module uses the IP Address that a user is connected from to deduce the
Country where the user is located.  This method is not foolproof, because
a user may connect through an anonymizing proxy, or may be in an unusual
situation, such as using an ISP from a neighboring country, or using an
IP block leased from a company in another country.  Additionally, users
accessing a server on a local network may be using an IP that is not assigned
to any country (e.g. 192.168.1.x or 127.0.0.1).

Country determination occurs upon user login.  If a country can be determined
from the IP address, the ISO 3166 2-character country code is stored in the
Drupal $user object as $user->country_iso_code_2.  If no country can be
determined, this member variable is left unset.

Rules support allows you to detect the user's country and take action
depending on the value.  For instance, you could have customized landing pages
for users from different countries, or show/hide content based on the user's
country (e.g. a product not available for sale in a certain country).  An
example Reaction Rule is included with this module - it will be automatically
installed but set to "disabled" so it will not run until you manually "enable"
it from the Rules UI.  You may edit this example for your own needs, or just use
it as reference.  The Rule is named "Login redirect - ES" and it demonstrates
how to redirect a user at login in time, based on the country of that user's
IP address.

Alternatively, a function is provided so that you may look up the country
from within your own code, for your own use.  The way to use this is:

  $ip = \Drupal::request()->getClientIp();
  $country_code = \Drupal::service('ip2country.lookup')->getCountry($ip);

This lookup service also works for anonymous visitors. It does not require a
logged-in account. Automatic country storage happens at login, so it should not
be confused with a lookup for the current request. Do not store a visitor's
country against the shared anonymous user ID. Integrations that personalize
anonymous responses must also account for page and render caching so one
visitor's result is not served to another.

Drupal core provides a function which can transform this $country_code into
a country name.  Use it like this:

  $country_list = \Drupal::service('country_manager')->getList();
  $country_name = $country_list[$country_code];


The database uses extended allocation statistics published by the five Regional
Internet Registries (RIRs). By default, "Use all registries" downloads each
registry's current HTTPS feed directly. The ARIN selection uses the same direct
feeds to preserve global coverage because ARIN's mirrors of the other registries
are obsolete. Existing ARIN settings do not need to be changed. Other selections
may use registry mirrors or cover fewer regions. Allocation data is not a
guarantee of a visitor's physical location.

If you have cron set up for your Drupal site, this IP to Country database will
be automatically updated at a frequency determined by the admin menu at
admin/config/people/ip2country.  A checkbox is provided to turn on/off
logging of database updates.  The default update frequency is 1 week, but it
can be adjusted from 1 day up to 4 weeks.  Because this module downloads a lot
of data during the update and because the database is very stable, shorter
database update times are not needed.

Database updates may also be performed manually by pressing the button at
admin/config/people/ip2country.  Note this will take time to
complete - do not interrupt the update process or the update will fail and you
will have to do it again.  (A failed update does *not* corrupt the database.)


Drush support
=============
There are three drush commands provided by this module:

  drush ip2country:update --registry=<registry>
      Updates the ip2country database from the specified registry, or from
      the default registry if not specified.

  drush ip2country:lookup <ip>
      Looks up the given IP address in the ip2country database displays the
      country associated with that IP address.

  drush ip2country:status
      Prints the time and data source (RIR) of the last database update.

Drush may also be used to configure any of the settings for this program.  For
example, to see all the settings, use:

  drush cget ip2country.settings

And to change the settings, use the corresponding drush cset.  For example, to
disable automatic database updates, use:

  drush cset ip2country.settings update_interval 0

Or to turn on debug country/IP spoofing, use:

  drush cset ip2country.settings debug true

More help may be found using drush help --filter=ip2country


Requirements
============
This module is tested to work with Drupal 10 (10.3 or later) or Drupal 11.

Troubleshooting
===============
Because it's not practical to log in from another country in order to test
these features, a debugging setting is provided to allow the administrator
to specify a Country or IP Address to simulate.  When debugging is enabled,
it only affects the country stored in the administrator's $user object.
To use this debugging setting, check the box "Admin Debug" at
admin/config/people/ip2country.  You must also specify either a Country to
simulate or an IP to simulate.  A notification will be printed across the top
of the page when you submit the form, letting you know that the debug feature
has successfully been turned on (or off).  The simulated Country or IP will be
used for the administrator until this feature is turned off in the admin menu.

When all else fails, read the comments in the code - there are some debugging
print statements left in that can be uncommented to see what is going on, and
most functions are described in the comments.
