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
		this._hint = null;
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
		if (this._hint) this._hint.reset();
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

		const data = this._data.emails;
		const tf = (data.total !== undefined && data.total !== -1);
		const vals = this._format_statistic_values();
		Status._element_list.forEach((id, pos) => {
			this._set_element_value(id, null, vals[pos], tf && Status._element_data[id].color || null);
		});

		const el = document.getElementById("stat-block");
		if (typeof(data.days) === "string") {
			el.setAttribute("title", "Statistics for " + data.days);
		}
		else {
			el.removeAttribute("title");
		}
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

	_set_element_value(c_el, v_el, value, cname) {
		if (typeof(c_el) == "string") c_el = document.getElementById("stat-" + c_el);
		if (cname) {
			c_el.setAttribute("class", "state-" + cname);
		} else {
			c_el.removeAttribute("class");
		}
		(v_el || c_el.querySelector(".stat-val")).textContent = value;
	}

	_format_statistic_values() {
		const de = this._data.emails;
		const total = de.total;
		const vals = [ (total === -1 || total === undefined) && "?" || Common.abbrNumber(total, 10000) ];
		[
			de.dkim_spf_aligned,
			de.dkim_aligned + de.spf_aligned,
			(total || 0) - de.dkim_spf_aligned - de.dkim_aligned - de.spf_aligned
		].forEach(v => vals.push(this._formatted_statistic(v, total)));
		return vals;
	}

	_ensure_element_created() {
		let block = document.getElementById("stat-block");
		if (block && block.children.length === 0) {
			const ul = block.appendChild(document.createElement("ul"));
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
			const hli = ul.appendChild(document.createElement("li"));
			hli.classList.add("hint");
			if (!this._hint) this._hint = new HintButton({ content: this._make_hint_content.bind(this) });
			hli.append(this._hint.element());
		}
	}

	_make_hint_content() {
		const data = this._data.emails;
		const el = document.createElement("div");
		const hd = el.appendChild(document.createElement("h4"));
		const tf = (data.total !== undefined && data.total !== -1);
		hd.textContent = tf && ("Statistics for " + data.days) || "n/a";
		const ul = el.appendChild(document.createElement("ul"));
		const vals = this._format_statistic_values();
		Status._element_list.forEach((id, pos) => {
			const li = ul.appendChild(document.createElement("li"));
			li.appendChild(document.createElement("span")).textContent = Status._element_data[id].text + ": ";
			const vl = li.appendChild(document.createElement("span"));
			vl.classList.add("stat-val", "state-text");
			this._set_element_value(li, vl, vals[pos], tf && Status._element_data[id].color || null);
		});
		return el;
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
		text:  "Emails processed",
		color: "blue"
	},
	passed: {
		text: "Fully aligned",
		color: "green"
	},
	forwarded: {
		text: "Partially aligned",
		color: "green"
	},
	failed:	{
		text: "Not aligned",
		color: "red"
	}
};
