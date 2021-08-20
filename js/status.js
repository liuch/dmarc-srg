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
	}

	update() {
		return this._fetch().then(function(data) {
			return data;
		}).catch(function(err) {
			console.warn(err.message);
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

	_fetch() {
		let that = this;
		return new Promise(function(resolve, reject) {
			window.fetch("status.php", {
				method: "GET",
				cache: "no-store",
				headers: HTTP_HEADERS,
				credentials: "same-origin"
			}).then(function(resp) {
				if (resp.status !== 200)
					throw new Error("Failed to fetch the status");
				return resp.json();
			}).then(function(data) {
				that._data.state = data.state;
				that._data.error_code = data.error_code;
				that._data.message = data.message;
				that._data.emails = data.emails;
				that._update_block();
				if (data.error_code === -2) {
					LoginDialog.start({ nousername: true });
				}
				resolve(data);
			}).catch(function(err) {
				that._data.state = "Err";
				that._data.error_code = -100;
				that._data.message = err.message;
				that._update_block();
				reject(err);
			});
		});
	}

	_update_block() {
		this._ensure_element_created();
		if (this._data.error_code) {
			Notification.add({ text: "[" + this._data.error_code + "] " + this._data.message, type: "error" });
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
		this._set_element_data("processed", total === -1 && "?" || total, total !== -1 && "state-blue" || null);
		this._set_element_data("passed", this._formatted_statistic(passed, total), total !== -1 && "state-green" || null);
		this._set_element_data("forwarded", this._formatted_statistic(forwarded, total), total !== -1 && "state-green" || null);
		this._set_element_data("failed", this._formatted_statistic(failed, total), total !== -1 && "state-red" || null);
		{
			let el = document.getElementById("stat-block");
			if (days > 0) {
				el.setAttribute("title", "Statistics for the last " + days + " days");
			}
			else {
				el.removeAttribute("title");
			}
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

