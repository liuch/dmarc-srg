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

class Settings {
	constructor() {
		this._table = null;
		this._scrool = null;
		this._sort   = "ascent";
		this._element = document.getElementById("main-block");
	}

	display() {
		remove_all_children(this._element);
		this._make_scroll_container();
		this._make_table();
		this._scroll.appendChild(this._table.element());
		this._element.appendChild(this._scroll);
		this._table.focus();
	}

	update() {
		this._fetch_settings();
	}

	title() {
		return "Advanced Settings";
	}

	_fetch_settings() {
		this._table.display_status("wait");
		let that = this;

		let uparams = new URLSearchParams();
		uparams.set("direction", this._sort);

		return window.fetch("settings.php?" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (resp.status !== 200)
				throw new Error("Failed to fetch the settings");
			return resp.json();
		}).then(function(data) {
			that._table.display_status(null);
			if (data.error_code !== undefined && data.error_code !== 0) {
				throw new Error(data.message || "Unknown error");
			}
			let d = { more: data.more };
			d.rows = data.settings.map(function(it) {
				return that._make_row_data(it);
			});
			that._table.clear();
			let fr = new ITableFrame(d, 0);
			that._table.add_frame(fr);
			that._table.focus();
		}).catch(function(err) {
			console.warn(err.message);
			that._table.display_status("error", err.message);
		});
	}

	_make_scroll_container() {
		this._scroll = document.createElement("div");
		this._scroll.setAttribute("class", "main-table-container");
	}

	_make_table() {
		this._table = new ITable({
			class:   "main-table",
			onclick: function(row) {
				let data = row.userdata();
				if (data) {
					this._display_edit_dialog(data);
				}
			}.bind(this),
			onsort: function(col) {
				let dir = col.sorted() && "toggle" || "ascent";
				this._table.set_sorted(col.name(), dir);
				this._sort = col.sorted();
				this.update();
			}.bind(this),
			onfocus: function(el) {
				scroll_to_element(el, this._scroll);
			}.bind(this)
		});
		[
			{ content: "Name", name: "name", sortable: true },
			{ content: "Value", name: "value" },
			{ content: "Description", name: "descr" }
		].forEach(function(col) {
			let c = this._table.add_column(col);
			if (c.name() === "name") {
				c.sort(this._sort);
			}
		}, this);
	}

	_make_row_data(d) {
		let rd = { cells: [], userdata: d.name };
		rd.cells.push({ content: d.name, class: "setting-name" });
		rd.cells.push({ content: d.value, class: "setting-value" });
		rd.cells.push({ content: Settings._descriptions_short[d.name] || Settings._descriptions[d.name] || "No description" });
		if (d.value !== d.default) {
			rd.class = "custom-value";
		}
		return rd;
	}

	_display_edit_dialog(name) {
		let dlg = new SettingEditDialog({
			name:        name,
			description: Settings._descriptions[name]
		});
		this._element.appendChild(dlg.element());
		let that = this;
		dlg.show().then(function(d) {
			if (d) {
				that.update();
			}
		}).finally(function() {
			dlg.element().remove();
			that._table.focus();
		});
	}

	static _descriptions = {
		"status.emails-for-last-n-days": "The period in days for which statistics are displayed in the status block.",
		"report-view.sort-records-by": "How records are sorted in the report view dialog.",
		"log-view.sort-list-by": "How report log items are sorted by default in the log view dialog.",
		"ui.datetime.offset": "Time zone offset of displayed dates in UI. Auto means that the report range is in UTC and all other dates are in local.",
		"ui.ipv4.url": "The URL that will be used as a link when clicking on the IPv4 address. For example: https://somewhoisservice.net/ip/{$ip}, where {$ip} is IP address from the UI. Use {$eip} if you want to insert url encoded IP address. Use an empty string to disable.",
		"ui.ipv6.url": "The URL that will be used as a link when clicking on the IPv6 address. For example: https://somewhoisservice.net/ip/{$ip}, where {$ip} is IP address from the UI. Use {$eip} if you want to insert url encoded IP address. Use an empty string to disable."
	};

	static _descriptions_short = {
		"ui.datetime.offset": "Time zone offset of displayed dates in UI.",
		"ui.ipv4.url": "The URL that will be used as a link when clicking on the IPv4 address.",
		"ui.ipv6.url": "The URL that will be used as a link when clicking on the IPv6 address."
	};
}

class SettingEditDialog extends ModalDialog {
	constructor(param) {
		super({ buttons: [ "ok", "close" ] });
		this._data    = param || {};
		this._content = null;
		this._table   = null;
		this._val_el  = null;
		this._val_tp  = null;
		this._desc_el = null;
		this._save_bt = null;
		this._fetched = false;
	}

	_gen_content() {
		this._table = document.createElement("div");
		this._table.setAttribute("class", "left-titled");
		this._content.appendChild(this._table);

		let nm = document.createElement("input");
		nm.setAttribute("type", "text");
		nm.setAttribute("disabled", "disabled");
		nm.setAttribute("value", this._data.name);
		this._insert_row("Name", nm);

		let val = document.createElement("input");
		val.setAttribute("type", "text");
		val.disabled = true;
		this._insert_row("Value", val);
		this._val_el = val;
		this._val_tp = "string";

		let desc = document.createElement("textarea");
		desc.setAttribute("disabled", "disabled");
		if (this._data.description) {
			desc.appendChild(document.createTextNode(this._data.description));
		}
		desc.classList.add("description");
		this._insert_row("Description", desc);
		this._desc_el = desc;

		this._save_bt = this._buttons[1];
		this._save_bt.disabled = true;

		this._table.addEventListener("input", function(event) {
			if (this._fetched && event.target == this._val_el) {
				let e_val = null;
				switch (this._val_tp) {
					case "string":
						e_val = this._val_el.value;
						break;
					case "integer":
						e_val = this._val_el.valueAsNumber;
						break;
				}
				this._save_bt.disabled = (e_val === this._data.value);
			}
		}.bind(this));

		this._fetch_data();
	}

	_add_button(container, text, type) {
		if (type == "submit") {
			text = "Save";
		}
		super._add_button(container, text, type);
	}

	_insert_row(text, val_el) {
		let sp = document.createElement("span");
		sp.appendChild(document.createTextNode(text + ": "));
		this._table.appendChild(sp);
		this._table.appendChild(val_el);
	}

	_fetch_data() {
		this._enable_ui(false);
		this._content.appendChild(set_wait_status());
		let uparams = new URLSearchParams();
		uparams.set("name", this._data.name);

		let that = this;
		window.fetch("settings.php?" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (resp.status != 200) {
				throw new Error("Failed to fetch setting data for " + that._data.name);
			}
			return resp.json();
		}).then(function(data) {
			if (data.error_code !== undefined && data.error_code !== 0) {
				throw new Error(data.message || "Unknown error");
			}
			that._data.value = data.value;
			that._update_ui(data);
			that._enable_ui(true);
			that._fetched = true;
		}).catch(function(err) {
			console.warn(err.message);
			that._content.appendChild(set_error_status(null, err.message));
		}).finally(function() {
			that._content.querySelector(".wait-message").remove();
		});
	}

	_enable_ui(en) {
		this._val_el.disabled = !en;
		this._update_first_last();
		if (this._first) {
			this._first.focus();
		}
	}

	_update_ui(data) {
		if (data.type !== this._val_tp) {
			let new_el = null;
			if (data.type == "integer") {
				new_el = document.createElement("input");
				new_el.setAttribute("type", "number");
				if (typeof(data.minimum) == "number") {
					new_el.setAttribute("min", data.minimum);
				}
				if (typeof(data.maximum) == "number") {
					new_el.setAttribute("max", data.maximum);
				}
			} else if (data.type == "select") {
				new_el = document.createElement("select");
				data.options.forEach(function(op) {
					let opt_el = document.createElement("option");
					opt_el.setAttribute("value", op);
					opt_el.appendChild(document.createTextNode(op));
					if (op === data.value) {
						opt_el.setAttribute("selected", "selected");
					}
					new_el.appendChild(opt_el);
				});
			}
			if (new_el) {
				new_el.setAttribute("required", "required");
				this._val_el.replaceWith(new_el);
				this._val_el = new_el;
			}
			this._val_tp = data.type;
		}
		this._val_el.value = data.value;
	}

	_submit() {
		this._save_bt.disabled = true;
		this._enable_ui(false);
		let em = this._content.querySelector(".error-message");
		if (em) {
			em.remove();
		}
		this._content.appendChild(set_wait_status());

		let body = {};
		body.name = this._data.name;
		if (this._val_tp == "integer") {
			body.value = this._val_el.valueAsNumber;
		}
		else {
			body.value = this._val_el.value;
		}
		body.action = "update";

		let that = this;
		window.fetch("settings.php", {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify(body)
		}).then(function(resp) {
			if (resp.status != 200) {
				throw new Error("Failed to update the setting");
			}
			return resp.json();
		}).then(function(data) {
			if (data.error_code !== undefined && data.error_code !== 0) {
				throw new Error(data.message || "Unknown error");
			}
			that._data.value = that._val_el.value;
			that._result = body;
			that.hide();
			Notification.add({ type: "info", text: (data.message || "Updated successfully!") });
		}).catch(function(err) {
			console.warn(err.message);
			that._content.appendChild(set_error_status(null, err.message));
			Notification.add({ type: "error", text: err.message });
		}).finally(function() {
			that._content.querySelector(".wait-message").remove();
			that._save_bt.disabled = false;
			that._enable_ui(true);
		});
	}
}

