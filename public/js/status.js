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

class Status {
	constructor() {
		this._data = {};
		this._use_filter = null;
	}

	update(params) {
		return this._fetch(params || {}).then(function(data) {
			return data;
		}).catch(function(err) {
			Common.displayError(err);
		});
	}

	reset() {
		this._data.emails = null;
		this._data.error_code = 0;
		this._update_block();
	}

	error() {
		return this._data.error_code && this._data.error_code !== 0 || false;
	}

	_fetch(params) {
		let url = new URL("status.php", document.location);
		let fields = [ "state", "user" ];
		let s_list = params.settings || [];
		if (params.page !== "list") this._use_filter = false;
		if (this._use_filter === null || (params.settings && params.settings.length)) {
			fields.push("settings");
			if (this._use_filter === null) s_list.push("status.emails-filter-when-list-filtered");
		}
		url.searchParams.set("fields", fields.join(","));
		if (s_list.length) url.searchParams.set("settings", s_list.join(","));

		return window.fetch(url, {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok) throw new Error("Failed to fetch the status");
			return resp.json();
		}).then(function(data) {
			User.name  = data.user && data.user.name || null;
			User.level = data.user && data.user.level || null;
			User.auth_type = data.auth_type;
			this._data = {
				state:      data.state,
				error_code: data.error_code,
				message:    data.message,
				emails:     null
			};
			this._update_block();
			if (data.error_code === -2) LoginDialog.start();
			if (!this.error()) {
				if (data.settings) {
					let uf = data.settings["status.emails-filter-when-list-filtered"] || null;
					this._use_filter = (uf === "yes");
				}
				this._fetch_statistics();
			}
			return data;
		}.bind(this)).catch(function(err) {
			this._data = {
				state:      "Err",
				error_code: -100,
				message:    err.message
			};
			this._update_block();
			throw err;
		}.bind(this));
	}

	_fetch_statistics() {
		let url = new URL("status.php", document.location);
		url.searchParams.set("fields", "emails");
		if (this._use_filter) {
			(new URL(document.location)).searchParams.getAll("filter[]").forEach(function(fval) {
				url.searchParams.append("filter[]", fval);
			});
		}
		window.fetch(url, {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok) throw new Error("Failed to fetch statistics");
			return resp.json();
		}).then(function(data) {
			Common.checkResult(data);
			this._data.emails = data.emails;
			this._update_block();
		}.bind(this)).catch(function(err) {
			Common.displayError(err);
			this._update_block();
			Notification.add({ text: err.message || "Error!", type: "error" });
		}.bind(this));
	}

	_update_block() {
		this._ensure_element_created();
		if (this._data.error_code) {
			let p = { text: "[" + this._data.error_code + "] " + this._data.message, type: "error" };
			if (this._data.error_code === -2)
				p.name = "auth";
			Notification.add(p);
		}
		if (!this._data.emails) {
			this._data.emails = {
				days: 0,
				total: -1,
				spf_aligned: 0,
				dkim_aligned: 0,
				dkim_spf_aligned: 0
			};
		}
		let days = this._data.emails.days;
		let total = this._data.emails.total;
		let passed = this._data.emails.dkim_spf_aligned;
		let forwarded = this._data.emails.dkim_aligned + this._data.emails.spf_aligned;
		let failed = total - passed - forwarded;
		this._set_element_data(
			"processed",
			(total === -1 || total === undefined) && "?" || this._formatted_number(total),
			total !== -1 && "state-blue" || null
		);
		this._set_element_data(
			"passed",
			this._formatted_statistic(passed, total),
			total !== -1 && "state-green" || null
		);
		this._set_element_data(
			"forwarded",
			this._formatted_statistic(forwarded, total),
			total !== -1 && "state-green" || null
		);
		this._set_element_data(
			"failed",
			this._formatted_statistic(failed, total),
			total !== -1 && "state-red" || null
		);
		{
			let el = document.getElementById("stat-block");
			if (typeof(days) === "string") {
				el.setAttribute("title", "Statistics for " + days);
			}
			else {
				el.removeAttribute("title");
			}
		}
	}

	_formatted_number(val) {
		if (val < 10000) return val.toLocaleString();
		let f = 1;
		let u = "";
		if (val >= 1000000000) {
			f = 1000000000;
			u = "G";
		} else if (val >= 1000000) {
			f = 1000000;
			u = "M";
		} else if (val >= 1000) {
			f = 1000;
			u = "K";
		}
		return (Math.round((val / f + Number.EPSILON) * 10) / 10).toLocaleString() + u;
	}

	_formatted_statistic(val, total) {
		if (total === -1)
			return "?";
		if (!total)
			return "-";
		if (val === 0)
			return "0";
		let rval = Math.round(val / total * 100);
		return (val > 0 && rval === 0 && "+" || "" ) + rval + "%";
	}

	_set_element_data(id, data, c_name) {
		let el1 = document.getElementById("stat-" + id);
		if (c_name)
			el1.setAttribute("class", c_name);
		else
			el1.removeAttribute("class");
		let el2 = el1.querySelector(".stat-val")
		el2.childNodes[0].nodeValue = data;
	}

	_ensure_element_created() {
		let block = document.getElementById("stat-block");
		if (block && block.children.length === 0) {
			let ul = document.createElement("ul");
			Status._element_list.forEach(function(id) {
				let li = document.createElement("li");
				let div = document.createElement("div");
				div.setAttribute("id", "stat-" + id);
				let val = document.createElement("span");
				val.setAttribute("class", "stat-val state-text");
				val.appendChild(document.createTextNode("?"));
				let msg = document.createElement("span");
				msg.setAttribute("class", "stat-msg");
				msg.appendChild(document.createTextNode(Status._element_data[id].text));
				div.appendChild(val);
				div.appendChild(msg);
				li.appendChild(div);
				ul.appendChild(li);
			});
			block.appendChild(ul);
		}
	}
}

Status.instance = function() {
	if (!this._instance)
		this._instance = new Status();
	return this._instance;
}

Status._element_list = [ "processed", "passed", "forwarded", "failed" ];

Status._element_data = {
	processed: {
		text:  "Emails processed"
	},
	passed: {
		text: "Fully aligned"
	},
	forwarded: {
		text: "Partially aligned"
	},
	failed:	{
		text: "Not aligned"
	}
};
