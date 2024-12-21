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

class Logs {
	constructor() {
		this._page = null;
		this._table = null;
		this._scroll = null;
		this._filter = null;
		this._element = document.getElementById("main-block");
		this._set_btn = null;
		this._set_dlg = null;
		this._fetching = false;
		this._sort = { column: "", direction: "" };
	}

	display() {
		this._gen_settings_button();
		this._make_page_container();
		this._make_scroll_container();
		this._make_table();
		this._scroll.append(this._table.element());
		this._page.append(this._scroll);
		this._element.append(this._page);
		this._ensure_settins_button();
		this._table.focus();
	}

	update() {
		this._filter = Common.getFilterFromURL(new URL(document.location));
		this._update_table();
		this._update_settings_button();
	}

	title() {
		return "Logs";
	}

	_fetch_list() {
		this._table.display_status("wait");
		this._fetching = true;

		let pos = this._table.last_row_index() + 1;

		let uparams = new URLSearchParams();
		uparams.set("position", pos);
		if (this._sort.column && this._sort.direction) {
			uparams.set("order", this._sort.column);
			uparams.set("direction", this._sort.direction);
		}
		if (this._filter) {
			[ "success", "source" ].forEach(function(nm) {
				if (this._filter[nm]) uparams.append("filter[]", `${nm}:${this._filter[nm]}`);
			}, this);
		}

		let that = this;
		return window.fetch("logs.php?" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to fetch the logs");
			return resp.json();
		}).then(function(data) {
			that._table.display_status(null);
			Common.checkResult(data);
			if (data.sorted_by) {
				let cname = data.sorted_by.column;
				let dir   = data.sorted_by.direction;
				if (that._sort.column !== cname || that._sort.direction !== dir) {
					that._sort.column = cname;
					that._sort.direction = dir;
					that._table.set_sorted(cname, dir);
				}
			}
			let d = { more: data.more };
			d.rows = data.items.map(function(it) {
				return new ITableRow(that._make_row_data(it));
			});
			let fr = new ITableFrame(d, pos);
			that._table.add_frame(fr);
			return fr;
		}).catch(function(err) {
			Common.displayError(err);
			that._table.display_status("error");
		}).finally(function() {
			that._fetching = false;
		});
	}

	_update_table() {
		this._table.clear();
		let that = this;
		let fr_cnt = -1;
		let again = function() {
			let fc = that._table.frames_count()
			if (fr_cnt < fc && that._scroll.scrollHeight <= that._scroll.clientHeight * 1.5) {
				fr_cnt = fc;
				that._fetch_list().then(function(frame) {
					if (frame && frame.more()) {
						again();
					}
					else {
						that._table.focus();
					}
				});
			}
			else {
				that._table.focus();
			}
		};
		again();
	}

	_make_page_container() {
		this._page = document.createElement("div");
		this._page.classList.add("page-container");
	}

	_make_scroll_container() {
		const el = document.createElement("div");
		el.classList.add("table-wrapper");
		el.addEventListener("scroll", event => {
			if (!this._fetching && el.scrollTop + el.clientHeight >= el.scrollHeight * 0.95) {
				if (this._table.frames_count() === 0 || this._table.more()) {
					this._fetch_list();
				}
			}
		});
		this._scroll = el;
	}

	_gen_settings_button() {
		if (!this._set_btn) {
			let btn = document.createElement("span");
			btn.setAttribute("class", "options-button");
			btn.appendChild(document.createTextNode("\u{2699}"));
			btn.addEventListener("click", function(event) {
				event.preventDefault();
				this._display_settings_dialog();
			}.bind(this));
			this._set_btn = btn;
		}
	}

	_ensure_settins_button() {
		let title_el = document.querySelector("h1");
		if (!title_el.contains(this._set_btn)) {
			title_el.appendChild(this._set_btn);
		}
	}

	_update_settings_button() {
		if (this._set_btn) {
			this._set_btn.classList[ this._filter ? "add" : "remove" ]("active");
		}
	}

	_make_table() {
		this._table = new ITable({
			class: "main-table small-cards",
			onclick: function(row) {
				let data = row.userdata();
				if (data) {
					this._display_item_dialog(data);
				}
			}.bind(this),
			onsort: function(col) {
				let dir = col.sorted() && "toggle" || "descent";
				this._table.set_sorted(col.name(), dir);
				this._sort.column = col.name();
				this._sort.direction = col.sorted();
				this._update_table();
			}.bind(this),
			onfocus: function(el) {
				scroll_to_element(el, this._scroll);
			}.bind(this)
		});
		[
			{ content: "", class: "cell-status" },
			{ content: "Domain", name: "domain" },
			{ content: "Source" },
			{ content: "Event time", sortable: true, name: "event_time" },
			{ content: "Message" }
		].forEach(function(col) {
			let c = this._table.add_column(col);
			if (c.name() === this._sort.column) {
				c.sort(this._sort.direction);
			}
		}, this);
	}

	_make_row_data(d) {
		let rd = { cells: [], userdata: { id: d.id } };
		rd.cells.push(new LogsResultCell(d.success));
		rd.cells.push({ content: d.domain, label: "Domain" });
		rd.cells.push({ content: d.source, label: "Source" });
		rd.cells.push({ content: (new Date(d.event_time)).toUIString(), label: "Event time" });
		rd.cells.push({ content: d.message, label: "Message" });
		return rd;
	}

	_display_item_dialog(data) {
		let dlg = new LogItemDialog(data);
		this._element.appendChild(dlg.element());
		let that = this;
		dlg.show().finally(function() {
			dlg.element().remove();
			that._table.focus();
		});
	}

	_display_settings_dialog() {
		let dlg = this._set_dlg;
		if (!this._set_dlg) {
			dlg = new ReportLogFilterDialog({ filter: this._filter });
			this._set_dlg = dlg;
		}
		this._element.appendChild(dlg.element());
		dlg.show().then(function(d) {
			if (d) {
				const url = new URL(document.location);
				url.searchParams.delete("filter[]");
				for (let k in d) {
					if (d[k]) url.searchParams.append("filter[]", `${k}:${d[k]}`);
				}
				window.history.replaceState(null, "", url);
				const f = Common.getFilterFromURL(url, this._filter);
				if (f !== undefined) {
					this._filter = f;
					this._update_table();
					this._update_settings_button();
				}
			}
		}.bind(this)).finally(function() {
			this._table.focus();
		}.bind(this));
	}
}

class LogsResultCell extends ITableCell {
	constructor(success, props) {
		props = props || {};
		let ca = (props.class || "").split(" ");
		ca.push(success && "state-green" || "state-red");
		props.class = ca.filter(function(s) { return s.length > 0; }).join(" ");
		super(success, props);
	}

	element() {
		if (!this._element) {
			super.element().setAttribute("data-label", "Result");
		}
		return this._element;
	}

	value(target) {
		if (target === "dom") {
			let div = document.createElement("div");
			div.setAttribute("class", "state-background status-indicator");
			if (!this.title) {
				div.setAttribute("title", this._content && "Ok" || "Failed");
			}
			return div;
		}
		return this._content;
	}
}

class LogItemDialog extends ModalDialog {
	constructor(data) {
		super({ title: "Log record", buttons: [ "close" ] });
		this._data    = data;
		this._table   = null;
		this._res_el  = null;
		this._dom_el  = null;
		this._time_el = null; // event_time
		this._rid_el  = null; // report_id
		this._file_el = null; // filename
		this._sou_el  = null; // source
		this._msg_el  = null; // message
	}

	_gen_content() {
		this._table = document.createElement("div");
		this._table.setAttribute("class", "left-titled");
		this._content.appendChild(this._table);

		this._time_el = this._insert_row("Event time");
		this._res_el  = this._insert_row("Result");
		this._res_el.setAttribute("class", "state-text");
		this._dom_el  = this._insert_row("Domain");
		this._rid_el  = this._insert_row("Report Id");
		this._file_el = this._insert_row("File name");
		this._sou_el  = this._insert_row("Source");
		this._msg_el  = this._insert_row("Message");

		this._fetch_data();
	}

	_insert_row(text) {
		let t_el = document.createElement("span");
		t_el.appendChild(document.createTextNode(text + ": "));
		this._table.appendChild(t_el);
		let v_el = document.createElement("span");
		this._table.appendChild(v_el);
		return v_el;
	}

	_fetch_data() {
		this.display_status("wait", "Getting data...");
		let uparams = new URLSearchParams();
		uparams.set("id", this._data.id);

		window.fetch("logs.php?" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(resp => {
			if (!resp.ok) throw new Error("Failed to fetch the log item");
			return resp.json();
		}).then(data => {
			Common.checkResult(data);
			this._data.domain     = data.domain;
			this._data.report_id  = data.report_id;
			this._data.event_time = new Date(data.event_time);
			this._data.filename   = data.filename;
			this._data.source     = data.source;
			this._data.success    = data.success;
			this._data.message    = data.message;
			this._update_ui();
		}).catch(err => {
			Common.displayError(err);
			this.display_status("error", err.message);
		}).finally(() => {
			this.display_status("wait", null);
		});
	}

	_update_ui() {
		this._time_el.textContent = this._data.event_time.toUIString();
		if (this._data.success) {
			this._res_el.textContent = "Ok";
			this._res_el.parentElement.classList.add("state-green");
		}
		else {
			this._res_el.textContent = "Failed";
			this._res_el.parentElement.classList.add("state-red");
		}
		this._dom_el.textContent  = this._data.domain || "n/a";
		this._rid_el.textContent  = this._data.report_id || "n/a";
		this._file_el.textContent = this._data.filename || "n/a";
		this._sou_el.textContent  = this._data.source;
		this._msg_el.textContent  = this._data.message || "n/a";
	}
}

class ReportLogFilterDialog extends VerticalDialog {
	constructor(params) {
		params ||= {};
		super({ title: "Filter settings", buttons: [ "apply", "reset" ] });
		this._data = params;
		this._ui_data = [
			{ name: "success", title: "Result" },
			{ name: "source", title: "Source" }
		];
	}

	show() {
		this._update_ui();
		return super.show();
	}

	_gen_content() {
		this._inputs = document.createElement("fieldset");
		this._inputs.name = "filter";
		this._inputs.classList.add("round-border", "titled-input");
		let lg = document.createElement("legend");
		lg.append("Filter by");
		this._inputs.appendChild(lg);
		this._content.appendChild(this._inputs);
		this._ui_data.forEach(function(ud) {
			const el = document.createElement("select");
			el.name = ud.name;
			ud.element = el;
			this._insert_input_row(ud.title, el);
		}, this);
		const frm = this._content.parentElement;
		[ [ "", "Any" ], [ "true", "Success" ], [ "false", "Failure" ] ].forEach(function(it) {
			const op = document.createElement("option");
			op.setAttribute("value", it[0]);
			op.append(it[1]);
			frm.success.appendChild(op);
		});
		[
			[ "", "Any" ], [ "uploaded_file", "Uploaded file" ], [ "email", "Mailbox" ],
			[ "directory", "Directory" ], [ "remotefs", "Remote FS" ]
		].forEach(function(it) {
			const op = document.createElement("option");
			op.setAttribute("value", it[0]);
			op.append(it[1]);
			frm.source.appendChild(op);
		});
	}

	_update_ui() {
		let f = this._data.filter;
		this._ui_data.forEach(function(ud) {
			ud.element.value = f && f[ud.name] || "";
		});
	}

	_submit() {
		this._result = {};
		this._data.filter = {};
		this._ui_data.forEach(function(ud) {
			const el = ud.element;
			const val = el.options[el.selectedIndex].value;
			this._result[ud.name] = val;
			this._data.filter[ud.name] = val;
		}, this);
		this.hide();
	}
}
