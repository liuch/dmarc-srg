/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2025 Aleksey Andreev (liuch)
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

function set_wait_status(el, text) {
	const wait = document.createElement("div");
	wait.classList.add("wait-message");
	wait.append(text || "Getting data...");
	if (el) el.replaceChildren(wait);
	return wait;
}

function set_error_status(el, text) {
	const err = document.createElement("div");
	err.classList.add("error-message");
	err.append(text || "Error!");
	if (el) el.replaceChildren(err);
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

function scroll_to_element(element, container) { // because scrollIntoView is poorly supported by browsers
	let diff = null;
	let e_rect = element.getBoundingClientRect();
	let c_rect = container.getBoundingClientRect();
	let height = Math.min(e_rect.height, 64);
	if (e_rect.top < c_rect.top + height * 2) {
		diff = e_rect.top - c_rect.top - height * 2;
	}
	else if (e_rect.bottom > c_rect.bottom - height) {
		diff = e_rect.bottom - c_rect.bottom + height;
	}
	if (diff) {
		container.scrollBy(0, diff);
	}
}

function bytes2size(bytes) {
	if (!bytes) {
		return "0 bytes";
	}
	const k = 1024;
	const sizes = [ 'bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ];
	const i = Math.floor(Math.log(bytes) / Math.log(k));
	return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

class Common {
	static initAriaMethods() {
		if (HTMLElement.idSequenceValue === undefined) HTMLElement.idSequenceValue = 0;
		if (!HTMLElement.prototype.getId) {
			HTMLElement.prototype.getId = function() {
				if (!this.id) this.id = `id-${++HTMLElement.idSequenceValue}`;
				return this.id;
			};
		}
		if (!HTMLElement.prototype.setAriaControls) {
			HTMLElement.prototype.setAriaControls = function(element) {
				this.setAttribute("aria-controls", element.getId());
			};
		}
		if (!HTMLElement.prototype.setAriaLabelledBy) {
			HTMLElement.prototype.setAriaLabelledBy = function(element) {
				this.setAttribute("aria-labelledby", element.getId());
			};
		}
	}

	static tuneDateTimeOutput(mode) {
		Date.prototype.outputMode = mode;
		if (!Date.prototype.toUIString) {
			Date.prototype.toUIString = function(prefer_utc) {
				if (this.outputMode === "local" || (this.outputMode === "auto" && !prefer_utc)) {
					return this.toLocaleString();
				}
				return this.toLocaleString(undefined, { timeZone: 'UTC', timeZoneName: 'short' });
			};
		}
		if (!Date.prototype.toUIDateString) {
			Date.prototype.toUIDateString = function(prefer_utc) {
				if (this.outputMode === "local" || (this.outputMode === "auto" && !prefer_utc)) {
					return this.toLocaleDateString(
						undefined,
						{ year:"numeric", month:"short", day:"numeric" }
					);
				}
				return this.toLocaleDateString(
					undefined,
					{ timeZone:"UTC", year:"numeric", month:"short", day:"numeric" }
				);
			};
		}
	}

	static makeIpElement(ip) {
		let url = null;
		let type = ip.includes(":") && 6 || 4;
		switch (type) {
			case 4:
				url = Common.ipv4_url;
				break;
			case 6:
				url = Common.ipv6_url;
				break;
		}
		let tn = document.createTextNode(ip);
		if (url) {
			const r = /^(!*)(.+)$/.exec(url);
			if (r && r[1].length <= 2) {
				url = r[2].replace("{$ip}", ip).replace("{$eip}", encodeURIComponent(ip));
				let el = document.createElement("a");
				el.setAttribute("href", url);
				if (r[1].length == 1) {
					el.target = "ip-info";
				} else if (r[1].length == 2) {
					el.target = "_blank";
				}
				el.appendChild(tn).title = "IP address information";
				return el;
			}
		}
		return tn;
	}

	static createReportResultElement(name, result, value) {
		const span = document.createElement("span");
		if (value) name += ": " + value;
		span.textContent = name;
		const atitle = [ result, value ].reduce((res, it, idx) => {
			if (it && (!idx || it !== res[0])) res.push(it);
			return res;
		}, []);
		if (atitle.length) span.title = atitle.join(": ");
		span.classList.add("report-result");
		if (result) span.classList.add("report-result-" + result);
		return span;
	}

	static checkResult(data) {
		if (data.error_code !== undefined && data.error_code !== 0) {
			throw data;
		}
	}

	static displayError(obj) {
		console.warn(obj.message || "Unknown error");
		if (!(obj instanceof Error) && obj.debug_info) {
			console.warn('Error code: ' + obj.debug_info.code);
			console.warn('Error content: ' + obj.debug_info.content);
		}
	}

	/**
	 * Reduces large numbers with the addition of postfixes K, M, B.
	 */
	static abbrNumber(val, min) {
		if (min !== undefined && val < min) return val.toLocaleString();
		let f = 1;
		let u = "";
		if (val >= 1e9) {
			f = 1e9;
			u = "B";
		} else if (val >= 1e6) {
			f = 1e6;
			u = "M";
		} else if (val >= 1e3) {
			f = 1e3;
			u = "K";
		}
		return (Math.round((val / f + Number.EPSILON) * 10) / 10).toLocaleString() + u;
	}

	/**
	 * Gets the filter value as an object from the passed url, compares it to the passed cfilter,
	 * returns the new value, or undefined if the filter has not changed.
	 */
	static getFilterFromURL(url, cfilter) {
		let cnt = 0;
		const nfilter = {};
		url.searchParams.getAll("filter[]").forEach(function(it) {
			let k = null;
			let v = null;
			const i = it.indexOf(":");
			if (i !== 0) {
				if (i > 0) {
					k = it.substr(0, i);
					v = it.substr(i + 1);
				} else {
					k = it;
					v = "";
				}
				nfilter[k] = v;
				++cnt;
			}
		});
		if (cfilter === undefined) return cnt ? nfilter : null;

		let changed = !cfilter && cnt > 0;
		if (!changed && cfilter) {
			let cnt2 = 0;
			changed = Object.keys(cfilter).some(function(k) {
				++cnt2;
				return cnt < cnt2 || cfilter[k] !== nfilter[k];
			}) || cnt !== cnt2;
		}
		return changed ? (cnt ? nfilter : null) : undefined;
	}
}

Common.initAriaMethods();
Common.tuneDateTimeOutput("auto");
