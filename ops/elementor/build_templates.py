#!/usr/bin/env python3
"""Generate Elvane's Elementor layouts as JSON (header, footer, home page).

Layouts are built only from Elementor / Elementor Pro core widgets plus
shortcodes of active plugins, so they do not depend on the old Mixtas
(Kitify / Royal addons) widgets. Styling lives in the child theme CSS
(class names below), so the design is versioned in git. Images are
referenced by media-library attachment ID (see IMAGES).

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
	"hero_left": (2797, "2026/06/3.2.png"),
	"hero_right": (2765, "2026/06/1.2.png"),
	"trousers": (2489, "2026/05/WhatsApp-Image-2026-05-24-at-5.02.22-PM.jpeg"),
	"cargo": (2430, "2026/05/baggy3.png"),
	"polo": (2400, "2026/05/1.png"),
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


def media_tile(seed, image_key, eyebrow, cta, url, css_class="elvane-tile"):
	"""Full-bleed photo with a centred caption, like the reference's collection tiles."""
	return container(seed, css_class, [
		widget(seed + "-img", "image", "elvane-tile__img", image=image(image_key), image_size="full",
			link_to="custom", link=link(url)),
		container(seed + "-cap", "elvane-tile__caption", [
			text_link(seed + "-eyebrow", eyebrow, url, "elvane-tile__eyebrow"),
			text_link(seed + "-cta", cta, url, "elvane-tile__cta"),
		]),
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
				text_link("hdr-account", "Login", "/my-account/", "elvane-textlink elvane-hide-mobile"),
				widget("hdr-search", "shortcode", "elvane-search", shortcode='[fibosearch layout="icon"]'),
				text_link("hdr-wishlist", "Wishlist", "/wishlist/", "elvane-textlink elvane-hide-mobile"),
				widget("hdr-cart", "shortcode", "elvane-bag", shortcode="[xoo_wsc_cart]"),
			]),
		]),
	]


def build_footer():
	return [
		container("ftr", "elvane-footer", [
			links_row("ftr-nav", [
				("Home", "/"),
				("Shop", "/shop/"),
				("About", "/about-us/"),
				("Contact", "/contact-us/"),
				("Track order", "/order-tracking/"),
				("FAQs", "/faqs/"),
			], "elvane-footer__nav"),
			links_row("ftr-social", [
				("Instagram", INSTAGRAM),
				("Facebook", FACEBOOK),
			], "elvane-footer__social"),
			container("ftr-bottom", "elvane-footer__bottom", [
				widget("ftr-copy", "text-editor", "elvane-footer__copy",
					editor="<p>© 2026, Elvane Clothing.</p>"),
				links_row("ftr-legal", [
					("Privacy policy", "/privacy-policy/"),
					("Terms of service", "/terms-of-use/"),
				], "elvane-footer__legal"),
			]),
		]),
	]


def build_home():
	return [
		# Hero: two portraits side by side with the brand line over them.
		# Sections marked elvane-stack stay pinned while the next one slides over them.
		container("home-hero", "elvane-hero elvane-stack", [
			widget("home-hero-l", "image", "elvane-hero__img", image=image("hero_left"), image_size="full",
				link_to="custom", link=link("/shop/")),
			widget("home-hero-r", "image", "elvane-hero__img", image=image("hero_right"), image_size="full",
				link_to="custom", link=link("/shop/")),
			container("home-hero-cap", "elvane-hero__caption", [
				widget("home-hero-title", "heading", "elvane-display", title="Less noise.<br>More style.",
					header_size="h1"),
				text_link("home-hero-cta", "Shop now", "/shop/", "elvane-tile__cta"),
			]),
		]),
		media_tile("home-trousers", "trousers", "Trousers", "Shop collection", "/product-category/trousers/",
			"elvane-tile elvane-stack"),
		container("home-split", "elvane-split elvane-stack", [
			media_tile("home-cargo", "cargo", "Cargo", "Shop now", "/product-category/trousers/cargo/",
				"elvane-tile elvane-tile--half"),
			media_tile("home-polo", "polo", "Polos", "Shop now", "/product-category/t-shirt/polo/",
				"elvane-tile elvane-tile--half"),
		]),
		container("home-new", "elvane-section", [
			container("home-new-head", "elvane-section__head", [
				widget("home-new-title", "heading", "elvane-section__title", title="New arrivals", header_size="h2"),
				text_link("home-new-all", "View all", "/shop/", "elvane-textlink"),
			]),
			widget("home-new-grid", "woocommerce-products", "elvane-products",
				columns="4", rows="2", query_post_type="product", query_orderby="date", query_order="desc",
				paginate=""),
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
