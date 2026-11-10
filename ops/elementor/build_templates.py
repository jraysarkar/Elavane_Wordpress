#!/usr/bin/env python3
"""Generate Elvane's Elementor layouts as JSON (header, footer, home page).

Layouts are built only from Elementor / Elementor Pro core widgets plus
shortcodes of active plugins, so they do not depend on the old Mixtas
(Kitify / Royal addons) widgets. Styling lives in the child theme CSS
(class names below), so the design is versioned in git. Images are
referenced by media-library attachment ID (see IMAGES); the home banners
are edited in the admin instead (elvane-store plugin, Banners menu).

Usage:  python3 build_templates.py <out-dir> <site-url>
Writes: header.json, footer.json, home.json
"""

import hashlib
import json
import sys
from pathlib import Path

ANNOUNCEMENT = "Free delivery on prepaid orders &nbsp;·&nbsp; Up to 30% off all products"
INSTAGRAM = "https://www.instagram.com/elvane.clothing_"
FACEBOOK = "https://www.facebook.com/share/1HsqCH4Cnt/"

# Media-library attachment IDs on the site (Elvane's own photography).
IMAGES = {
	"logo": (2986, "2026/10/elvane-logo-black.png"),  # source: ops/brand/
}

SITE = ""


def _id(seed):
	return hashlib.md5(seed.encode()).hexdigest()[:7]


def _zero():
	return {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}


def image(key):
	attachment_id, path = IMAGES[key]
	return {"url": f"{SITE}/wp-content/uploads/{path}", "id": attachment_id, "alt": "", "source": "library"}


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


def links_row(seed, items, css_class):
	return widget(seed, "icon-list", css_class, view="inline", icon_list=[
		{"_id": _id(seed + text), "text": text, "link": link(url, url.startswith("http")), "selected_icon": icon()}
		for text, url in items
	])


def build_header():
	return [
		container("ann", "elvane-announcement", [
			widget("ann-text", "html", html=f'<p class="elvane-announcement__text">{ANNOUNCEMENT}</p>'),
		]),
		container("hdr", "elvane-header", [
			container("hdr-l", "elvane-header__left", [
				widget("hdr-menu", "nav-menu", "elvane-nav",
					menu="elvane-main", layout="horizontal", pointer="none",
					submenu_icon=icon(), dropdown="tablet", toggle="burger", full_width="stretch"),
			]),
			container("hdr-c", "elvane-header__center", [
				widget("hdr-logo", "image", "elvane-logo",
					image={**image("logo"), "alt": "Elvane Clothing"}, image_size="full",
					link_to="custom", link=link("/")),
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
	return [
		# As on the live site: only the social links and the two policy pages.
		container("ftr", "elvane-footer", [
			links_row("ftr-social", [
				("Instagram", INSTAGRAM),
				("Facebook", FACEBOOK),
			], "elvane-footer__social"),
			container("ftr-bottom", "elvane-footer__bottom", [
				widget("ftr-copy", "text-editor", "elvane-footer__copy",
					editor="<p>© 2026, Elvane Clothing.</p>"),
				links_row("ftr-legal", [
					("Privacy policy", "/privacy-policy/"),
					("Terms & conditions", "/terms-of-use/"),
				], "elvane-footer__legal"),
			]),
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
