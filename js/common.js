/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020 Aleksey Andreev (liuch)
 *
 * Available at:
 * https://github.com/liuch/dmarc-srg
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */

const HTTP_HEADERS = {
	"Accept": "application/json"
};

const HTTP_HEADERS_POST = {
	"Content-Type": "application/json"
};

function remove_all_children(el) {
	while (el.children.length > 0)
		el.removeChild(el.children[0]);
	while (el.childNodes.length > 0)
		el.removeChild(el.childNodes[0]);
}

function set_wait_status(el, text) {
	let wait = document.createElement("div");
	wait.setAttribute("class", "wait-message");
	wait.appendChild(document.createTextNode(text || "Getting data..."));
	if (el) {
		remove_all_children(el);
		el.appendChild(wait);
	}
	return wait;
}

function set_error_status(el, text) {
	let err = document.createElement("div");
	err.setAttribute("class", "error-message");
	err.appendChild(document.createTextNode(text || "Error!"));
	if (el) {
		remove_all_children(el);
		el.appendChild(err);
	}
	return err;
}

function date_range_to_string(d1, d2) {
	let s1 = d1.toISOString().substr(0, 10);
	let s2 = d2.toISOString().substr(0, 10);
	if (s1 !== s2) {
		let d3 = new Date(d2);
		d3.setSeconds(d3.getSeconds() - 1);
		if (s1 !== d3.toISOString().substr(0, 10))
			s1 += " - " + s2;
	}
	return s1;
}

function create_report_result_element(name, value, long_rec, result) {
	let span = document.createElement("span");
	if (long_rec)
		span.appendChild(document.createTextNode(name + ": " + value));
	else
		span.appendChild(document.createTextNode(name));
	span.setAttribute("title", value);
	let extra_class = "";
	if (result === undefined || result !== "")
		extra_class = " report-result-" + (result || value);
	span.setAttribute("class", "report-result" + extra_class);
	return span;
}

function scroll_to_element(element, container) { // because scrollIntoView is poorly supported by browsers
	let diff = null;
	let e_rect = element.getBoundingClientRect();
	let c_rect = container.getBoundingClientRect();
	if (e_rect.top < c_rect.top + e_rect.height * 2) {
		diff = e_rect.top - c_rect.top - e_rect.height * 2;
	}
	else if (e_rect.bottom > c_rect.bottom - e_rect.height) {
		diff = e_rect.bottom - c_rect.bottom + e_rect.height;
	}
	if (diff) {
		container.scrollBy(0, diff);
	}
}

