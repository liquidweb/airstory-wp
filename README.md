# Airstory WordPress Plugin

[![Build Status](https://travis-ci.org/liquidweb/airstory-wp.svg?branch=develop)](https://travis-ci.org/liquidweb/airstory-wp)
[![Code Climate](https://codeclimate.com/github/liquidweb/airstory-wp/badges/gpa.svg)](https://codeclimate.com/github/liquidweb/airstory-wp)
[![Test Coverage](https://codeclimate.com/github/liquidweb/airstory-wp/badges/coverage.svg)](https://codeclimate.com/github/liquidweb/airstory-wp/coverage)

This plugin enables [Airstory](http://www.airstory.co/) users to connect their WordPress sites, enabling authors to leverage the exceptional editorial experience of Airstory with the powerful publishing of WordPress.

## Requirements

* This plugin requires an active [Airstory](http://www.airstory.co/) subscription.
	* Not already an Airstory user? [Get one project free for life, just by signing up!](http://www.airstory.co/pricing/)
* PHP version 5.3 or higher, with the DOM, Mcrypt, and OpenSSL extensions active.
* The WordPress site must have a valid SSL certificate in order for Airstory to publish content.


## Installation

After installing and activating the Airstory WordPress plugin, WordPress authors are able to connect their Airstory accounts through the user profiles:

1. In Airstory, [navigate to the "My Account" panel](https://app.airstory.co/projects?overlay=account) by clicking the avatar in the bottom-left corner of the screen and copy the "User token" to your clipboard.
2. Within WordPress, navigate to "Users &rsaquo; Your Profile" and scroll to "Airstory Configuration". In the "User Token" field, paste your Airstory user token, then click the "Update Profile" button at the bottom of the profile page to save your changes.

If your token has been verified successfully, the "Airstory Configuration" section of your profile will show details about your Airstory account. You're all set to start publishing!


## Usage

Once Airstory is connected with WordPress, your site name will appear as an export destination within Airstory.

Exporting to WordPress will create a new *draft* post, enabling you to set post thumbnails, publish dates, categories, and anything else your post might need before publishing.


## Actions and filters

For developers, the Airstory WordPress plugin contains a number of [actions and filters that can be used to modify its default behavior](https://codex.wordpress.org/Plugin_API). For a full list of available filters, please [see the wiki in the plugin's GitHub repository](https://github.com/liquidweb/airstory-wp/wiki/Actions-and-Filters).


## Frequently Asked Questions

Answers to some of the more common questions authors may have.


### Does the Airstory plugin support WordPress Multisite?

Absolutely! If a user has authoring capabilities on multiple sites, a list of those sites will be available on the user's profile. Connect one site or connect them all, Airstory makes it easy to connect to WordPress.


### Can I re-export a document from Airstory to WordPress?

As long as the post that was generated by Airstory is still a draft in WordPress, re-exporting the same document from Airstory will keep updating the post content of that draft. Go ahead, export an early draft and continue writing in Airstory — your posts won't be overflowing with drafts.

Once a post has been published, Airstory will cease to update that post, and will instead create a new draft.


### The plugin won't activate, instead telling me "the Airstory plugin is missing one or more of its dependencies, so it's automatically been deactivated". How do I resolve this?

This is a safety feature built into the plugin to avoid any unexpected behavior due to missing dependencies. The Airstory plugin relies on two common PHP extensions: "dom" (for <abbr title="Document Object Model">DOM</abbr> manipulation, used to clean up incoming content from Airstory) and "openssl" (used to securely encrypt your Airstory user token before storing it).

All modern hosts (Liquid Web, WP Engine, SiteGround, etc.) should support these extensions out of the box, but if you're running your own server you'll want to [ensure these extensions are both installed and activated](https://www.liquidweb.com/kb/how-to-check-php-modules-with-phpinfo/).
