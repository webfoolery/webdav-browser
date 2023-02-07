# Webdav Browser
**Browse webdav locations, unlock stuck files**

Please note this is not entirely finished & may never be, but it does what it set out to do.

A tool to solve a problem... WebDAV is still alive & well despite its age and and better alternatives. I do some work for an organisation that relies quite heavily on its distrubuted benefits and for the most part it works well. Every now & then a file gets stuck with a lock despite nobody having it open. I suspect the culprit is a Windows 10 machine with Microsoft sign-in features (and gets confused with OneDrive & other nonsense), but short of reinstalling Win10 I can't seem to resolve this intermittent problem.

Until now!

Having delved into the [webDAV protocol](https://tools.ietf.org/html/rfc4918) at some length I realised that with a little effort I could speak a little webDAV, and within a short time I had a couple of cURL lines that let me unlock those files that were stuck. Although this was a working solution it wasn't something I could easily commit to memory so I set about making an interface that could be a bit more flexible, informative and functional.

**The webDAV browser was conceived**

One day I might write up some better documentation but I'm not really even sure if anybody will ever read this. So I'll kick off with a short version and leave with good intentions to expand at some point.

* Credentials go in the relevant boxes and a task is not requred.
* Hit send (a better name could be chosen).
* If you've got everything right so far you should see a list of assests (files, folders) on the right of the page. Any that are currently locked will appear in a table above the main browser table for easy access.
* Clicking a directory name (actually anywhere on the table row) will browse into that directory with a page load.
* Clicking on a file (actually anywhere on the table row) brings up the properties of the file.
* In the properties block (if the file is locked) you'll see an _Unlock_ button, and clicking that will remove the file lock - providing the credentials that you entered are those of the user that has the file locked.
* If you need to use different credentials to unlock the file you can enter them in the _altUsername_ and _altPassword_ inputs next to the _Unlock_ button.
* Below the main interface is an accordion with some debugging data that may be useful at times.

I don't yet see a way to unlock a file without knowing the users credentials.

The cURL method: 

    curl -X PROPFIND '{path-to-resource}' -H 'Authorization:Basic {base64 encoded username:password}' -H 'Depth:1'

    curl -X UNLOCK '{path-to-resource}' -H 'Authorization: Basic {base64 encoded username:password}' -H 'Lock-Token: <{lock-token-from-first-request}>'

# Changelog

* 2021-03-05 Adds Dockerfile & docker-compose to spin the tool up in Docker

* 2020-09-08 Adds task to show all locked files by recursing the file structure from the user defined endpoint. NB this can be slow so max_execution_time is set to 1200 in webdav.php