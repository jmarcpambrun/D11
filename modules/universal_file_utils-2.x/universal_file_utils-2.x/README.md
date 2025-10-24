Universal File Utils
====================
Provides an event-based system for checking file access and file
downloadability.

This module does nothing on its own and is for developers to simplify their
task when deciding whether a file can be displayed or downloaded.

Client modules should have event subscribers for either (or both):

- UniversalFileDownloadEvent
- UniversalFileAccessEvent

The file download event allows a module to decide whether it wants to permit
the download (call `$event->denyDownload()` if not) and can also add headers
if that's needed. An event subscriber will need to be created for that.

The file access event allows a module to decide whether a file (probably an
image) can be viewed or downloaded.

There is a base event subscriber for this which should be extended, the
client module only needs to provide some data through two methods to decide
whether access is permitted.

Apart from checking download and access, the service also provides the ability
to copy and remove files.


