#!/usr/bin/env python3
"""Generate Elvane's Elementor layouts as JSON (header, footer, home page).

Layouts are built only from Elementor / Elementor Pro core widgets plus
shortcodes of active plugins, so they do not depend on the old Mixtas
(Kitify / Royal addons) widgets. Styling lives in the child theme CSS
(class names below), so the design is versioned in git. Everything a shop
owner changes — banners, announcement, social links, logo, menu categories —
is managed in the admin, not here (elvane-store plugin, Banners menu).

Usage:  python3 build_templates.py <out-dir> <site-url>
Writes: header.json, footer.json, home.json
"""

import hashlib
import json
import sys
from pathlib import Path

SITE = ""


def _id(seed):
	return hashlib.md5(seed.encode()).hexdigest()[:7]


def _zero():
	return {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}


def link(url, external=False):
	return {"url": url, "is_external": "on" if external else "", "nofollow": "", "custom_attributes": ""}


def icon(value="", library=""):
	return {"value": value, "library": library}


def container(seed, css_class, children, **settings):
	base = {"content_width": "full", "css_classes": css_class, "padding": _zero(), "margin": _zero()}
	base.update(settings)
	return {"id": _id(seed), "elType": "container", "isInner": False, "settings": base, "elements": children}


def widget(seed, widget_type, css_class="", **settings):
	if css_class:
		settings["_css_classes"] = css_class
	return {"id": _id(seed), "elType": "widget", "widgetType": widget_type, "settings": settings, "elements": []}


def text_link(seed, text, url, css_class="elvane-textlink"):
	return widget(seed, "heading", css_class, title=text, link=link(url), header_size="span")


def build_header():
	return [
		container("ann", "elvane-announcement", [
			# Messages from Banners → Site settings (elvane-store plugin).
			widget("ann-text", "shortcode", shortcode="[elvane_announcement]"),
		]),
		container("hdr", "elvane-header", [
			container("hdr-l", "elvane-header__left", [
				widget("hdr-menu", "nav-menu", "elvane-nav",
					menu="elvane-main", layout="horizontal", pointer="none",
					submenu_icon=icon(), dropdown="tablet", toggle="burger", full_width="stretch"),
			]),
			container("hdr-c", "elvane-header__center", [
				# The Site Logo (Appearance → Customize → Site Identity).
				widget("hdr-logo", "theme-site-logo", "elvane-logo", image_size="full", link_to="site_url",
					alt="Elvane Clothing",
					__dynamic__={"image": '[elementor-tag id="" name="site-logo" settings="%7B%7D"]'}),
			]),
			container("hdr-r", "elvane-header__right", [
				# Label switches to "Account" for logged-in customers (CSS).
				text_link("hdr-account", "Login", "/my-account/", "elvane-textlink elvane-account-link elvane-hide-mobile"),
				widget("hdr-search", "shortcode", "elvane-search", shortcode='[fibosearch layout="icon"]'),
				text_link("hdr-wishlist", "Wishlist", "/wishlist/", "elvane-textlink elvane-hide-mobile"),
				widget("hdr-cart", "shortcode", "elvane-bag", shortcode="[xoo_wsc_cart]"),
			]),
		]),
	]


def build_footer():
	# Social links, copyright and policy pages come from the elvane-store plugin
	# (Banners → Site settings, Settings → Privacy, WooCommerce → Advanced).
	return [
		container("ftr", "elvane-footer", [
			widget("ftr-content", "shortcode", shortcode="[elvane_footer]"),
		]),
	]


def build_home():
	# Banners and New arrivals come from the elvane-store plugin: banners are
	# edited in the admin (Banners menu), so this page only holds the shortcode.
	# One block, so the full-screen banners stay pinned while the next slides over.
	return [
		container("home", "elvane-home-page", [
			widget("home-banners", "shortcode", shortcode="[elvane_home]"),
		]),
	]


def main():
	global SITE
	out, SITE = Path(sys.argv[1]), sys.argv[2].rstrip("/")
	out.mkdir(parents=True, exist_ok=True)
	for name, build in (("header", build_header), ("footer", build_footer), ("home", build_home)):
		(out / f"{name}.json").write_text(json.dumps(build()))
	print("wrote header.json, footer.json, home.json to", out)


if __name__ == "__main__":
	main()
