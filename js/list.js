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

class ReportList {
	constructor() {
		this._table = null;
		this._scroll = null;
		this._filter = null;
		this._sort = { column: "begin_time", direction: "descent" };
		this._element = document.getElementById("main-block");
		this._element2 = document.getElementById("detail-block");
		this._fetching = false;
		this._settings_btn = null;
		this._settings_dlg = null;
	}

	display() {
		this._gen_settings_button();
		this._gen_content_container();
		this._gen_table();
		this._scroll.appendChild(this._table.element());
		this._element.appendChild(this._scroll);
		this._ensure_report_widget();
		this._element2.appendChild(ReportWidget.instance().element());
		this._ensure_settins_button();
		ReportWidget.instance().hide();
		this._table.focus();
	}

	update() {
		this._handle_url_params();
		this._update_table();
	}

	title() {
		return "Report List";
	}

	onpopstate() {
		if (!this._scroll) {
			this.display();
			this.update();
		}
		else {
			if (!this._element.contains(this._scroll)) {
				remove_all_children(this._element);
				this._element.appendChild(this._scroll);
			}
			if (this._handle_url_params()) {
				this._update_table();
			}
		}
		this._ensure_settins_button();
		this._ensure_report_widget();
		if (this._table) {
			this._table.focus();
		}
	}

	_ensure_settins_button() {
		let title_el = document.querySelector("h1");
		if (!title_el.contains(this._settings_btn)) {
			title_el.appendChild(this._settings_btn);
		}
	}

	_ensure_report_widget() {
		let wdg = ReportWidget.instance();
		wdg.hide();
		let el = wdg.element();
		if (!this._element2.contains(el)) {
			this._element2.appendChild(el);
		}
	}

	/**
	 * Sets the _filter object from the document's location
	 * and updates the setting button if the filter has changed
	 *
	 * @return bool True if the filter was changed, false otherwise
	 */
	_handle_url_params() {
		let cnt = 0;
		let filter = {};
		(new URL(document.location.href)).searchParams.getAll("filter[]").forEach(function(it) {
			let k = null;
			let v = null;
			let i = it.indexOf(":");
			if (i != 0) {
				if (i > 0) {
					k = it.substr(0, i);
					v = it.substr(i + 1);
				}
				else {
					k = it;
					v = "";
				}
				filter[k] = v;
				++cnt;
			}
		});
		let changed = !this._filter && cnt > 0;
		if (this._filter) {
			let cnt2 = 0;
			changed = Object.keys(this._filter).some(function(k) {
				++cnt2;
				return cnt < cnt2 || this._filter[k] !== filter[k];
			}, this) || cnt !== cnt2;
		}
		if (changed) {
			this._filter = cnt && filter || null;
			this._update_settings_button();
		}
		return changed;
	}

	_gen_settings_button() {
		if (!this._settings_btn) {
			let btn = document.createElement("span");
			btn.setAttribute("class", "options-button");
			btn.appendChild(document.createTextNode("\u{2699}"));
			let that = this;
			btn.addEventListener("click", function(event) {
				that._display_settings_dialog();
				event.preventDefault();
			});
			this._settings_btn = btn;
		}
	}

	_update_settings_button() {
		if (this._settings_btn) {
			if (this._filter)
				this._settings_btn.classList.add("active");
			else {
				this._settings_btn.classList.remove("active");
			}
		}
	}

	_gen_content_container() {
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

	_gen_table() {
		this._table = new ReportTable({
			class:   "main-table report-list small-cards",
			onclick: function(row) {
				let data = row.userdata();
				if (data)
					this._display_report(data, row.id());
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
			{ content: "Domain" },
			{ content: "Date", sortable: true, name: "begin_time" },
			{ content: "Reporting Organization" },
			{ content: "Report ID", class: "report-id" },
			{ content: "Messages" },
			{ content: "Result" }
		].forEach(function(col) {
			let c = this._table.add_column(col);
			if (c.name() === this._sort.column) {
				c.sort(this._sort.direction);
			}
		}, this);
	}

	_update_table() {
		this._table.clear();
		let that = this;
		let frcnt = -1;
		let again = function() {
			if (frcnt < that._table.frames_count() && that._scroll.clientHeight * 1.5 >= that._scroll.scrollHeight) {
				frcnt = that._table.frames_count();
				that._fetch_list().then(function(frame) {
					if (frame && frame.more())
						again();
					else
						that._table.focus();
				});
			}
			else
				that._table.focus();
		}
		again();
	}

	_display_report(data, id) {
		if (data.domain && data.report_id) {
			let url = new URL("report.php", document.location.href);
			url.searchParams.set("domain", data.domain);
			url.searchParams.set("report_id", data.report_id);
			window.history.pushState(null, "", url.toString());
			let that = this;
			ReportWidget.instance().show_report(data.domain, data.report_id).then(function() {
				if (!that._table.seen(id)) {
					that._table.seen(id, true);
				}
			}).catch(function(err) {
				console.warn(err.message);
				LoginDialog.start({ nousername: true });
			});
			Router.update_title(ReportWidget.instance().title());
			ReportWidget.instance().focus();
		}
	}

	_fetch_list() {
		this._table.display_status("wait");
		this._fetching = true;

		let pos = this._table.last_row_index() + 1;

		let uparams = new URLSearchParams();
		uparams.set("list", "reports");
		uparams.set("position", pos);
		uparams.set("order", this._sort.column);
		uparams.set("direction", this._sort.direction);
		if (this._filter) {
			for (let nm in this._filter) {
				uparams.append("filter[]", nm + ":" + this._filter[nm]);
			}
		}

		let that = this;
		return window.fetch("list.php?" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (resp.status !== 200)
				throw new Error("Failed to fetch the report list");
			return resp.json();
		}).then(function(data) {
			that._table.display_status(null);
			if (data.error_code !== undefined && data.error_code !== 0)
				throw new Error(data.message || "Unknown error");
			let d = { more: data.more };
			d.rows = data.reports.map(function(it) {
				return new ReportTableRow(that._make_row_data(it));
			});
			let fr = new ITableFrame(d, pos);
			that._table.add_frame(fr);
			return fr;
		}).catch(function(err) {
			console.warn(err.message);
			that._table.display_status("error", err.message);
		}).finally(function() {
			that._fetching = false;
		});
	}

	_make_row_data(d) {
		let rd = { cells: [], userdata: { domain: d.domain, report_id: d.report_id }, seen: d.seen && true || false }
		rd.cells.push({ content: d.domain, label: "Domain" });
		let d1 = new Date(d.date.begin);
		let d2 = new Date(d.date.end);
		rd.cells.push({ content: date_range_to_string(d1, d2), title: d1.toUIString(true) + " - " + d2.toUIString(true), label: "Date" });
		rd.cells.push({ content: d.org_name, label: "Reporting Organization" });
		rd.cells.push({ content: d.report_id, class: "report-id" });
		rd.cells.push({ content: d.messages, label: "Messages" });
		rd.cells.push(new StatusColumn({ dkim_align: d.dkim_align, spf_align: d.spf_align }));
		return rd;
	}

	_display_settings_dialog() {
		let dlg = this._settings_dlg;
		if (!this._settings_dlg) {
			dlg = new ReportListSettingsDialog({ filter:  this._filter });
			this._settings_dlg = dlg;
		}
		this._element.appendChild(dlg.element());
		let that = this;
		dlg.show().then(function(d) {
			if (d) {
				let url = new URL(document.location.href);
				url.searchParams.delete("filter[]");
				for (let k in d) {
					if (d[k]) {
						url.searchParams.append("filter[]", k + ":" + d[k]);
					}
				}
				window.history.replaceState(null, "", url.toString());
				remove_all_children(that._element);
				that.display();
				that.update();
			}
		}).finally(function() {
			that._table.focus();
		});
	}
}

class ReportTable extends ITable {
	seen(row_id, flag) {
		let row = super._get_row(row_id);
		if (row) {
			if (flag === undefined)
				return row.seen();
			row.seen(flag);
		}
	}
}

class ReportTableRow extends ITableRow {
	constructor(data) {
		super(data);
		this._seen = data.seen && true || false;
	}

	element() {
		if (!this._element) {
			super.element();
			this._update_seen_element();
		}
		return this._element;
	}

	seen(flag) {
		if (flag === undefined)
			return this._seen;

		this._seen = flag && true || false;
		if (this._element)
			this._update_seen_element();
	}

	_update_seen_element() {
		if (this._seen)
			this._element.classList.remove("unseen");
		else
			this._element.classList.add("unseen");
	}
}

class StatusColumn extends ITableCell {
	element() {
		if (!this._element) {
			super.element().setAttribute("data-label", "Result");
		}
		return this._element;
	}

	value(target) {
		if (target === "dom") {
			let d = this._content;
			let fr = document.createDocumentFragment();
			if (d.dkim_align) {
				fr.appendChild(create_report_result_element("DKIM", d.dkim_align));
			}
			if (d.spf_align) {
				fr.appendChild(create_report_result_element("SPF", d.spf_align));
			}
			return fr;
		}
		return super.value(target);
	}
}

class ReportListSettingsDialog extends ModalDialog {
	constructor(params) {
		super({ title: "List display settings", buttons: [ "apply", "reset" ] });
		this._data    = params || {};
		this._content = null;
		this._ui_data = [
			{ name: "domain", title: "Domain" },
			{ name: "month", title: "Month" },
			{ name: "organization", title: "Organization" },
			{ name: "dkim", title: "DKIM result" },
			{ name: "spf", title: "SPF result" },
			{ name: "status", title: "Status" }
		];
	}

	show() {
		this._update_ui();
		return super.show();
	}

	_gen_content() {
		let fs = document.createElement("fieldset");
		fs.setAttribute("class", "round-border titled-input");
		let lg = document.createElement("legend");
		lg.appendChild(document.createTextNode("Filter by"));
		fs.appendChild(lg);
		this._ui_data.forEach(function(ud) {
			let el = this._create_select_label(ud.title, fs);
			ud.element = el;
		}, this);
		this._content.appendChild(fs);
		this._content.classList.add("vertical-content");
		if (!this._data.loaded_filters)
			this._fetch_data();
	}

	_create_select_label(text, c_el) {
		let lb = document.createElement("label");
		let sp = document.createElement("span");
		sp.appendChild(document.createTextNode(text + ": "));
		lb.appendChild(sp);
		let sl = document.createElement("select");
		lb.appendChild(sl);
		c_el.appendChild(lb);
		return sl;
	}

	_enable_ui(enable) {
		let list = this._element.querySelector("form").elements;
		for (let i = 0; i < list.length; ++i)
			list[i].disabled = !enable;
	}

	_update_ui() {
		this._update_filters();
	}

	_update_filters() {
		let data = this._data.loaded_filters || {};
		let vals = this._data.filter || {};
		this._ui_data.forEach(function(ud) {
			this._update_select_element(ud.element, data[ud.name], vals[ud.name]);
		}, this);
	}

	_update_select_element(sl, d, v) {
		remove_all_children(sl);
		let ao = document.createElement("option");
		ao.setAttribute("value", "");
		ao.setAttribute("selected", "selected");
		ao.appendChild(document.createTextNode("Any"));
		sl.appendChild(ao);
		let v2 = "";
		if (d) {
			let op = null;
			d.forEach(function(fs) {
				op = document.createElement("option");
				op.setAttribute("value", fs);
				op.appendChild(document.createTextNode(fs));
				if (fs === v) {
					v2 = v;
				}
				sl.appendChild(op);
			}, this);
		}
		sl.value = v2;
	}

	_submit() {
		let res = {};
		let fdata = {};
		this._ui_data.forEach(function(ud) {
			let el = ud.element;
			let val = el.options[el.selectedIndex].value;
			res[ud.name] = val;
			fdata[ud.name] = val;
		});
		this._data.filter = fdata;
		this._result = res;
		this.hide();
	}

	_fetch_data() {
		let that = this;
		this._enable_ui(false);
		this._content.appendChild(set_wait_status());
		window.fetch("list.php?list=filters", {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (resp.status !== 200)
				throw new Error("Failed to fetch the filter list");
			return resp.json();
		}).then(function(data) {
			if (data.error_code !== undefined && data.error_code !== 0)
				throw new Error(data.message || "Unknown error");
			that._data.loaded_filters = data.filters;
			that._update_ui();
			that._enable_ui(true);
		}).catch(function(err) {
			console.warn(err.message);
			that._content.appendChild(set_error_status());
		}).finally(function() {
			that._content.querySelector(".wait-message").remove();
		});
	}
}

