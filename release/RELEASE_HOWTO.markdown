Midgard Release HOWTO
=====================

This document explains the steps needed for making a Midgard release.

Roles:

* Release Manager
* Commit Masters of components


1. Pre Packaging Tasks of Release Manager
-----------------------------------------

* Version number should be chosen
  ("10.05.1" will be used for example-purposes in this document)
* Release plan should be announced to Commit Masters of all packages


2. Tasks of Commit Masters
--------------------------

The end-goal of Commit Master is to create tag named according to release's
version number

* Create temporary release-branch starting from desired point of generation
  branch. In the basic case it's just a HEAD of generation branch, but
  sometimes it can be some specific commit, which is considered to be stable.
  For example:

        git checkout ratatoskr
        git checkout -b 10.05.1-tmp

* On this temporary branch change version-numbers mentioned in code. In case of
  our example, it would mean:
  change all occurrences of "10.05.0.99" with "10.05.1"
  
  this command will help you to do such replaces:

        rpl -R '10.05.0.99' '10.05.1' .


* commit changes

        git commit -a -m 'bump version'

* create tag, return to generation-branch and remove temporary branch

        git tag 10.05.1
        git checkout ratatoskr
        git branch -d 10.05.1-tmp

* change version-numbers in generation-branch and commit

        rpl -R '10.05.0.99' '10.05.1.99' .
        git commit -a -m 'bump version'

* push changes to github

        git push
        git push --tags

* Create new version-tag in github's issue-tracker "10.05.2"

* Retag all unresolved issues in github's tracker which had "10.05.1" tag


3. Packaging Tasks of Release Manager
-------------------------------------

* Get confirmation from Commit Masters, that their packages are ready and tagged

* In midgard-support-tools/release issue the command, which will grab snapshots
  of all packages from github and pack them in proper tar.gz files:

        pake pack_all 10.05.1

* Upload packages to http://www.midgard-project.org/download/
  The article should use the major version (for example 2.0)
  as the URL name

* Ensure that binary packages get generated and uploaded:
  * upload tarballs to OBS service
  * upload all files changed in dists/OBS

* Upload source debian package to maemo extras-devel repository
  * Download debian files (dsc, targ.gz, diff) to separate directory
    (Files need to be downloaded from OBS package management project page.
    https://build.opensuse.org/project/show?project=home%3Amidgardproject%3Aratatoskr%3Anogir)
  * For each package run `dpkg-source -x PACKAGE_NAME`
  * For each unpacked source run `dpkg-buildpackage -rfakeroot -sa -S`
  * Upload generated source, diff and tar.gz files to extras-devel
    (using dput or maemo packages web interface https://garage.maemo.org/extras-assistant/index.php)

* Upload source debian packages to debian unstable or experimental repository

* Generate core docs (core/midgard/docs use gtk-doc) and upload them to midgard
  website

* Generate PHP API bindings viewer and upload (apis/php5/docs/make_html.php)
  and upload them to midgard website.

* Bump version for next release.
  - configure.in in core
  - setup.py in python module
  - options.yml in support-tools
  - OBS files

* Remove old tarballs from OBS dev repos

4. Announcements
----------------

The release announcement should be made available both as HTML and plain text.
It should contain the following information:

* Release name, generation name and number (Midgard 10.05.1 Ratatoskr "We hope
  this works" released)

* Release location and date (Lodz, May 5th 2010)

* Explanation (The Midgard Project has released ...)

* Bullet list of major new features from both Midgard Core/APIs and Midgard MVC

* Descriptions of the biggest improvements in few paragraphs

* What is Midgard: basic description or marketing text and URL
  to www.midgard-project.org

* Planned for next release: features, release date estimate

* Download URLs for source and binaries

* URLs for "Getting Started" guide and bug reporting

* Contact information for release manager, spokesman and mailing lists

Email release announcement to following addresses:

- dev@midgard-project.org
- user@midgard-project.org
- lwn@lwn.net 

Submit release announcement by web to following locations:

- http://www.midgard-project.org/updates/
- http://freshmeat.net/projects/midgard
- http://freshmeat.net/projects/midcom
- http://www.cmsinfo.org/submission.php3
- http://www.content-wire.com/contact
- http://linuxtoday.com/contribute.php3
- http://apache.slashdot.org/submit.pl
- https://www.entwickler.com/ssl/php_forms/pme_general.php?check=1
- http://cmsmatrix.org/matrix/news?func=add&class=WebGUI::Asset::Post::Thread
- http://www.qaiku.com/channels/show/midgard/
- http://jaiku.com/channel/midgard
- http://www.new.facebook.com/group.php?gid=27554626815

Also update the entry on Midgard in:

- http://en.wikipedia.org/wiki/Midgard_(software)
- http://www.macupdate.com/info.php/id/19132

Add the release name together with linked explanation to:

- http://www.midgard-project.org/midcom-permalink-d528f84c55ef2299a96c8e9e3ccb5252


5. Major releases
-----------------

When making a major Midgard release (i.e. 10.05.0 or 9.09.0), the following
additional actions must be done:

* Create a release information sub-site to http://www.m-p.org/midgard/<version>
  - It should contain at least screenshots, feature list, architecture
    diagram, and a more verbose and nicely formatted version of the
    announcement
  - This sub-site can be created already early on in the release cycle
    and kept up-to-date, as the final feature set of the release is clarified
  - It is important to note both developer-oriented and end-user features
