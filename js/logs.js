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
		this._table = null;
		this._scroll = null;
		this._element = document.getElementById("main-block");
		this._fetching = false;
		this._sort = { column: "", direction: "" };
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

		let that = this;
		return window.fetch("logs.php?" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (resp.status !== 200)
				throw new Error("Failed to fetch the logs");
			return resp.json();
		}).then(function(data) {
			that._table.display_status(null);
			if (data.error_code !== undefined && data.error_code !== 0) {
				throw new Error(data.message || "Unknown error");
			}
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
			console.warn(err.message);
			that._table.display_status("error");
		}).finally(function() {
			that._fetching = false;
		});
	}

	_make_scroll_container() {
		let that = this;
		let el = document.createElement("div");
		el.setAttribute("class", "main-table-container");
		el.addEventListener("scroll", function() {
			if (!that._fetching && el.scrollTop + el.clientHeight >= el.scrollHeight * 0.95) {
				if (that._table.frames_count() === 0 || that._table.more()) {
					that._fetch_list();
				}
			}
		});
		this._scroll = el;
	}

	_make_table() {
		this._table = new ITable({
			class: "main-table",
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
				this.update();
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
		rd.cells.push({ content: d.domain });
		rd.cells.push({ content: d.source });
		rd.cells.push({ content: (new Date(d.event_time)).toUIString() });
		rd.cells.push({ content: d.message });
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
}

class LogsResultCell extends ITableCell {
	constructor(success, props) {
		props = props || {};
		let ca = (props.class || "").split(" ");
		ca.push(success && "state-green" || "state-red");
		props.class = ca.filter(function(s) { return s.length > 0; }).join(" ");
		super(success, props);
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
		super({ buttons: [ "close" ] });
		this._data    = data;
		this._table   = null;
		this._res_el  = null;
		this._dom_el  = null;
		this._time_el = null; // event_time
		this._rid_el  = null; // external_id
		this._file_el = null; // filename
		this._sou_el  = null; // source
		this._msg_el  = null; // message
	}

	_gen_content() {
		this._table = document.createElement("div");
		this._table.setAttribute("class", "table");
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
		let row = document.createElement("div");
		row.setAttribute("class", "row");
		let sp = document.createElement("span");
		sp.setAttribute("class", "cell");
		sp.appendChild(document.createTextNode(text + ":"));
		row.appendChild(sp);
		let val_el = document.createElement("span");
		val_el.classList.add("cell");
		row.appendChild(val_el);
		this._table.appendChild(row);
		return val_el;
	}

	_fetch_data() {
		this._content.appendChild(set_wait_status());
		let uparams = new URLSearchParams();
		uparams.set("id", this._data.id);

		let that = this;
		window.fetch("logs.php?" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (resp.status != 200) {
				throw new Error("Failed to fetch the log item");
			}
			return resp.json();
		}).then(function(data) {
			if (data.error_code !== undefined && data.error_code !== 0) {
				throw new Error(data.message || "Unknown error");
			}
			that._data.domain     = data.domain;
			that._data.report_id  = data.report_id;
			that._data.event_time = new Date(data.event_time);
			that._data.filename   = data.filename;
			that._data.source     = data.source;
			that._data.success    = data.success;
			that._data.message    = data.message;
			that._update_ui();
		}).catch(function(err) {
			console.warn(err.message);
			that._content.appendChild(set_error_status(null, err.message));
		}).finally(function() {
			that._content.querySelector(".wait-message").remove();
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

