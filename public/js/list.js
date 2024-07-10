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
		this._filter = Common.getFilterFromURL(new URL(document.location));
		this._update_table();
		this._update_settings_button();
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
			if (!this._element.contains(this._scroll)) this._element.replaceChildren(this._scroll);
			const f = Common.getFilterFromURL(new URL(document.location), this._filter);
			if (f !== undefined) {
				this._filter = f;
				Status.instance().reset();
				Status.instance().update({ page: "list" });
				this._update_table();
				this._update_settings_button();
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
			{ content: "Result" },
			{ content: "Disposition" },
			{ content: "Quarantined" },
			{ content: "Rejected" }
		].forEach(col => {
			let c = this._table.add_column(col);
			if (c.name() === this._sort.column) {
				c.sort(this._sort.direction);
			}
		});
		this._table.set_columns_visible([ 0, 1, 2, 4, 5, 6 ]);
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
		if (data.domain && data.time && data.org && data.report_id) {
			let url = new URL("report.php", document.location);
			url.searchParams.set("org", data.org);
			url.searchParams.set("time", data.time);
			url.searchParams.set("domain", data.domain);
			url.searchParams.set("report_id", data.report_id);
			window.history.pushState({ from: "list" }, "", url);
			let that = this;
			let filter = this._filter && {
				dkim: this._filter.dkim || "",
				spf: this._filter.spf || "",
				disposition: this._filter.disposition || ""
			} || null;
			ReportWidget.instance().show_report(data.domain, data.time, data.org, data.report_id, filter).then(function() {
				if (!that._table.seen(id)) {
					that._table.seen(id, true);
				}
			}).catch(function(err) {
				Common.displayError(err);
				if (err.error_code && err.error_code === -2) {
					LoginDialog.start();
				}
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
			if (!resp.ok)
				throw new Error("Failed to fetch the report list");
			return resp.json();
		}).then(function(data) {
			that._table.display_status(null);
			Common.checkResult(data);
			let d = { more: data.more };
			d.rows = data.reports.map(function(it) {
				return new ReportTableRow(that._make_row_data(it));
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

	_make_row_data(d) {
		let rd = {
			cells: [],
			userdata: { domain: d.domain, time: d.date.begin, org: d.org_name, report_id: d.report_id },
			seen: d.seen && true || false
		};
		rd.cells.push({ content: d.domain, label: "Domain" });
		let d1 = new Date(d.date.begin);
		let d2 = new Date(d.date.end);
		rd.cells.push({
			content: date_range_to_string(d1, d2),
			title: d1.toUIString(true) + " - " + d2.toUIString(true),
			label: "Date"
		});
		rd.cells.push({ content: d.org_name, label: "Reporting Organization" });
		rd.cells.push({ content: d.report_id, class: "report-id" });
		rd.cells.push({ content: d.messages, label: "Messages" });
		rd.cells.push(new ResultColumn({ dkim_align: d.dkim_align, spf_align: d.spf_align }, { label: "Result" }));
		rd.cells.push(new DispositionColumn({
			none: d.messages - d.rejected - d.quarantined,
			rejected: d.rejected,
			quarantined: d.quarantined
		}, { label: "Disposition" }));
		rd.cells.push(new FailsColumn(d.quarantined, { label: "Quarantined" }));
		rd.cells.push(new FailsColumn(d.rejected, { label: "Rejected" }));
		return rd;
	}

	_display_settings_dialog() {
		let dlg = this._settings_dlg;
		if (!this._settings_dlg) {
			dlg = new ReportListSettingsDialog({ filter:  this._filter });
			this._settings_dlg = dlg;
		}
		this._element.appendChild(dlg.element());
		dlg.show().then(function(d) {
			if (d) {
				let url = new URL(document.location.href);
				url.searchParams.delete("filter[]");
				for (let k in d) {
					if (d[k]) {
						url.searchParams.append("filter[]", k + ":" + d[k]);
					}
				}
				window.history.replaceState(null, "", url);
				const f = Common.getFilterFromURL(url, this._filter);
				if (f !== undefined) {
					this._filter = f;
					Status.instance().reset();
					Status.instance().update({ page: "list" });
					this._update_table();
					this._update_settings_button();
				}
			}
		}.bind(this)).finally(function() {
			this._table.focus();
		}.bind(this));
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

class ResultColumn extends ITableCell {
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

class DispositionColumn extends ITableCell {
	value(target) {
		if (target === "dom") {
			const fr = document.createDocumentFragment();
			const d = this._content;
			if (d.none) fr.append(create_report_result_element("None", d.none, true, "pass"));
			if (d.quarantined) fr.append(create_report_result_element("Quar", d.quarantined, true, "fail"));
			if (d.rejected) fr.append(create_report_result_element("Rej", d.rejected, true, "fail"));
			return fr;
		}
		return super.value(target);
	}
}

class ColoredIntColumn extends ITableCell {
	_make_colored_int_element(value, factor) {
		const el = document.createElement("span");
		factor *= value;
		if (factor) el.classList.add("report-result-" + (factor > 0 ? "pass" : "fail"));
		el.append(value);
		return el;
	}
}

class FailsColumn extends ColoredIntColumn {
	value(target) {
		if (target !== "dom") return super.value(target);
		return this._make_colored_int_element(this._content, -1);
	}
}

class ReportListSettingsDialog extends ReportFilterDialog {
	constructor(params) {
		params.title = "List display settings";
		params.item_list = [ "domain", "month", "organization", "dkim", "spf", "disposition", "status" ];
		super(params);
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
			if (!resp.ok)
				throw new Error("Failed to fetch the filter list");
			return resp.json();
		}).then(function(data) {
			Common.checkResult(data);
			that._data.loaded_filters = data.filters;
			that._update_ui();
			that._enable_ui(true);
		}).catch(function(err) {
			Common.displayError(err);
			that._content.appendChild(set_error_status());
		}).finally(function() {
			that._content.querySelector(".wait-message").remove();
		});
	}
}
