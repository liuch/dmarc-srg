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

class ITable {
	constructor(params) {
		this._table = null;
		this._class = null;
		this._header = null;
		this._status = null;
		this._frames = [];
		this._columns = [];
		this._body = null;
		this._onsort = null;
		this._onclick = null;
		this._onfocus = null;
		if (params) {
			this._class = params.class || null;
			this._onsort = params.onsort || null;
			this._onclick = params.onclick || null;
			this._onfocus = params.onfocus || null;
			this._nodata_text = params.nodata_text || null;
		}
		this._focused = false;
		this._focused_row = null;
		this._selected_rows = [];
	}

	element() {
		if (!this._table) {
			let that = this;
			this._table = document.createElement("div");
			if (this._class)
				this._table.setAttribute("class", this._class);
			this._table.classList.add("table");
			this._table.setAttribute("tabindex", -1);
			this._table.addEventListener("focus", function(event) {
				that._focused = true;
				that._update_focus();
			}, true);
			this._table.addEventListener("blur", function(event) {
				that._focused = false;
				that._update_focus();
			}, true);
			let th = document.createElement("div");
			th.setAttribute("class", "table-header");
			this._table.appendChild(th);
			this._header = document.createElement("div");
			this._header.setAttribute("class", "table-row");
			this._header.addEventListener("click", function(event) {
				let col = that.get_column_by_element(event.target);
				if (col && col.is_sortable()) {
					if (that._onsort) {
						that._onsort(col);
					}
				}
			});
			th.appendChild(this._header);
			this._fill_columns();
			this._body = document.createElement("div");
			this._body.setAttribute("class", "table-body");
			this._body.addEventListener("click", function(event) {
				let row = that._get_row_by_element(event.target);
				if (row) {
					that._set_selected_rows([ row ]);
					if (that._onclick)
						that._onclick(row);
				}
			});
			this._body.addEventListener("focus", function(event) {
				let row = that._get_row_by_element(event.target);
				if (row) {
					that._update_focused_row(row);
					if (that._onfocus)
						that._onfocus(row.element());
				}
			}, true);
			this._body.addEventListener("blur", function(event) {
				let row = that._get_row_by_element(event.target);
				if (row) {
					row.onfocus(false);
				}
			}, true);
			this._body.addEventListener("keydown", function(event) {
				let row = null;
				switch (event.code) {
					case "ArrowDown":
						row = that._get_row(that._focused_row !== null && (that._focused_row.id() + 1) || 0);
						break;
					case "ArrowUp":
						if (that._focused_row) {
							let id = that._focused_row.id();
							if (id >= 0)
								row = that._get_row(id - 1);
						}
						else {
							row = that._get_row(0);
						}
						break;
					case "PageUp":
						if (that._focused_row && that._frames.length > 0) {
							let c_id = that._focused_row.id();
							let f_fr = that._frames[0];
							let f_id = f_fr.first_index();
							if (c_id == f_id)
								break;
							let s_el = that._get_scroll_element();
							if (s_el) {
								let r_ht = that._focused_row.element().getBoundingClientRect().height;
								let s_ht = s_el.getBoundingClientRect().height;
								let n_id = Math.max(c_id - Math.floor(s_ht / r_ht) - 1, f_id);
								row = that._get_row(n_id);
							}
							else {
								row = f_fr.row(f_id);
							}
						}
						break;
					case "PageDown":
						if (that._focused_row && that._frames.length > 0) {
							let c_id = that._focused_row.id();
							let l_fr = that._frames[that._frames.length - 1];
							let l_id = l_fr.last_index();
							if (c_id == l_id)
								break;
							let s_el = that._get_scroll_element();
							if (s_el) {
								let r_ht = that._focused_row.element().getBoundingClientRect().height;
								let s_ht = s_el.getBoundingClientRect().height;
								let n_id = Math.min(c_id + Math.floor(s_ht / r_ht) - 1, l_id);
								row = that._get_row(n_id);
							}
							else {
								row = l_fr.row(l_id);
							}
						}
						break;
					case "Home":
						if (that._frames.length > 0) {
							let first_frame = that._frames[0];
							row = first_frame.row(first_frame.first_index());
						}
						break;
					case "End":
						if (that._frames.length > 0) {
							let last_frame = that._frames[that._frames.length - 1];
							row = last_frame.row(last_frame.last_index());
						}
						break;
					case "Enter":
					case "NumpadEnter":
						if (that._onclick && that._focused_row)
							that._onclick(that._focused_row);
						event.preventDefault();
						return;
				}
				if (row) {
					row.element().focus();
					that._set_selected_rows([ row ]);
					event.preventDefault();
				}
			});
			this._fill_frames();
			this._table.appendChild(this._body);
		}
		return this._table;
	}

	more() {
		return this._frames.length > 0 && this._frames[this._frames.length - 1].more();
	}

	frames_count() {
		return this._frames.length;
	}

	add_column(data) {
		let col = new ITableColumn(data.content, {
			name:     data.name,
			class:    data.class,
			sortable: data.sortable,
			sorted:   data.sorted
		});
		this._columns.push(col);
		if (this._header)
			this._header.appendChild(col.element());
		return col;
	}

	get_column_by_element(el) {
		el = el && el.closest("div.table-cell");
		if (el) {
			for (let i = 0; i < this._columns.length; ++i) {
				let col = this._columns[i];
				if (el === col.element())
					return col;
			}
		}
	}

	display_status(status, text) {
		if (this._status && !status) {
			this._status.remove();
			this._status = null;
			return;
		}

		this.element();
		this._status = document.createElement("div");
		this._status.setAttribute("class", "table-row colspanned");
		let el = document.createElement("div");
		el.setAttribute("class", "table-cell");
		this._status.appendChild(el);
		let el2 = document.createElement("div");
		el2.setAttribute("class", "table-cell");
		el2.appendChild(document.createTextNode("\u00A0")); // Non breaking space
		this._status.appendChild(el2);
		if (status === "wait") {
			set_wait_status(el);
		}
		else {
			remove_all_children(this._body);
			if (status === "nodata") {
				el.classList.add("nodata");
				el.appendChild(document.createTextNode(text || "No data"));
			}
			else {
				set_error_status(el, text);
			}
		}
		this._body.appendChild(this._status);
	}

	last_row_index() {
		let idx = -1;
		if (this._frames.length > 0) {
			idx = this._frames[this._frames.length - 1].last_index();
		}
		return idx;
	}

	add_frame(frame) {
		if (frame.count() === 0) {
			if (this._frames.length === 0)
				this.display_status("nodata", this._nodata_text);
			return
		}

		if (this._frames.length > 0 && this._frames[0].first_index() > frame.last_index()) {
			this._frames.unshift(frame);
			if (this._body)
				this._body.insertBefore(frame.element(), this._body.firstChild);
		}
		else {
			this._frames.push(frame);
			if (this._body)
				this._body.appendChild(frame.element());
		}
	}

	clear() {
		this._frames = [];
		if (this._body)
			remove_all_children(this._body);
		this._focused_row = null;
		this._selected_rows = [];
	}

	focus() {
		if (!this._focused_row) {
			if (this._frames.length > 0) {
				let fr = this._frames[0];
				this._focused_row = fr.row(fr.first_index());
			}
		}
		if (this._focused_row)
			this._focused_row.element().focus();
	}

	sort(col_name, direction) {
		if (this._frames.length == 1) {
			for (let i = 0; i < this._columns.length; ++i) {
				let col = this._columns[i];
				if (col.is_sortable() && col.name() === col_name) {
					let fr = this._frames[0];
					fr.sort(i, direction);
					if (this._body) {
						remove_all_children(this._body);
						this._body.appendChild(fr.element());
					}
					return;
				}
			}
		}
	}

	set_sorted(col_name, direction) {
		this._columns.forEach(function(col) {
			if (col.is_sortable()) {
				if (col.name() !== col_name) {
					col.sort(null);
				}
				else {
					if (direction === "toggle") {
						direction = null;
						if (col.sorted() === "ascent") {
							direction = "descent";
						}
						else if (col.sorted() === "descent") {
							direction = "ascent";
						}
					}
					col.sort(direction);
				}
			}
		});
	}

	_fill_columns() {
		this._columns.forEach(function(col) {
			this._header.appendChild(col.element());
		}, this);
	}

	_fill_frames() {
		this._frames.forEach(function(fr) {
			this._body.appendChild(fr.element());
		}, this);
	}

	_get_row(row_id) {
		for (let i = 0; i < this._frames.length; ++i) {
			let fr = this._frames[i];
			if (fr.last_index() >= row_id) {
				if (fr.first_index() <= row_id)
					return fr.row(row_id);
			}
		}
		return null;
	}

	_get_row_by_element(el) {
		let row = null;
		if (el) {
			el = el.closest("div.table-row");
			if (el) {
				let id = parseInt(el.getAttribute("data-id"));
				if (id !== NaN)
					row = this._get_row(id);
			}
		}
		return row;
	}

	_update_focus() {
		if (this._focused)
			this._table.classList.add("focused");
		else
			this._table.classList.remove("focused");
	}

	_update_focused_row(row) {
		if (this._focused_row && row !== this._focused_row) {
			this._focused_row.tabindex(-1);
		}
		this._focused_row = row;
		this._focused_row.tabindex(0);
		this._focused_row.onfocus(true);
	}

	_set_selected_rows(rows) {
		this._selected_rows.forEach(function(row) {
			row.select(false);
		});
		rows.forEach(function(row) {
			row.select(true);
		});
		this._selected_rows = rows;
	}

	_get_scroll_element() {
		let t_rect = this._table.getBoundingClientRect();
		let p_elem = this._table.parentElement;
		while (p_elem) {
			let p_rect = p_elem.getBoundingClientRect();
			if (t_rect.top < p_rect.top || t_rect.bottom > p_rect.bottom) {
				return p_elem;
			}
			p_elem = p_elem.paretnElement;
		}
	}
}

class ITableFrame {
	constructor(data, pos) {
		this._pos = pos;
		this._more = data.more && true || false;
		let id = pos;
		this._rows = data.rows.map(function(rd) {
			if (!(rd instanceof ITableRow)) {
				rd = new ITableRow(rd);
			}
			rd.id(id++);
			return rd;
		});
	}

	count() {
		return this._rows.length;
	}

	first_index() {
		return this._pos;
	}

	last_index() {
		let cnt = this._rows.length;
		if (cnt > 0) {
			return this._pos + cnt - 1;
		}
		return null;
	}

	row(id) {
		let idx = id - this._pos;
		if (idx >= 0 && idx < this._rows.length) {
			return this._rows[idx];
		}
		return null;
	}

	more() {
		return this._more;
	}

	element() {
		let fr = document.createDocumentFragment();
		this._rows.forEach(function(row) {
			fr.appendChild(row.element());
		});
		return fr;
	}

	sort(col_idx, direction) {
		let dir = (direction === "ascent" && 1) || (direction === "descent" && 2) || 0;
		if (dir) {
			let that = this;
			this._rows.sort(function(a, b) {
				let c1 = a.cell(col_idx);
				let c2 = b.cell(col_idx);
				if (dir === 1) {
					return that._compare_cells(c2, c1);
				}
				return that._compare_cells(c1, c2);
			});
			let id = this._pos;
			this._rows.forEach(function(row) {
				row.id(id++);
			});
		}
	}

	_compare_cells(c1, c2) {
		return c1.value("sort") < c2.value("sort");
	}
}

class ITableRow {
	constructor(data) {
		this._id = -1;
		this._focused = false;
		this._tabindex = -1;
		this._selected = false;
		this._element = null;
		this._class = data.class || null;
		this._userdata = data.userdata || null;
		this._cells = data.cells.map(function(col) {
			if (col instanceof ITableCell) {
				return col;
			}
			let props = null;
			if (col.title || col.class || col.label) {
				props = {
					title: col.title || null,
					class: col.class || null,
					label: col.label || null
				};
			}
			return new ITableCell(col.content, props);
		});
	}

	userdata() {
		return this._userdata;
	}

	element() {
		if (!this._element) {
			this._element = document.createElement("div");
			this._element.setAttribute("data-id", this._id);
			if (this._class)
				this._element.setAttribute("class", this._class);
			this._element.classList.add("table-row");
			this._cells.forEach(function(col) {
				this._element.appendChild(col.element());
			}, this);
			this._update_focus();
			this._update_tabindex();
			this._update_select();
		}
		return this._element;
	}

	onfocus(flag) {
		this._focused = flag;
		if (this._element)
			this._update_focus();
	}

	tabindex(index) {
		if (this._tabindex !== index) {
			this._tabindex = index;
			this._update_tabindex();
		}
	}

	select(flag) {
		this._selected = flag;
		if (this._element)
			this._update_select();
	}

	id(new_id) {
		if (new_id !== undefined && new_id !== this._id) {
			this._id = new_id;
			if (this._element) {
				this._element.setAttribute("data-id", this._id);
			}
		}
		return this._id;
	}

	cell(index) {
		return this._cells[index] || null;
	}

	_update_focus() {
		if (this._focused)
			this._element.classList.add("focused");
		else
			this._element.classList.remove("focused");
	}

	_update_tabindex() {
		this._element.setAttribute("tabindex", this._tabindex);
	}

	_update_select() {
		if (this._selected) {
			this._element.classList.add("selected");
		}
		else {
			this._element.classList.remove("selected");
		}
	}
}

class ITableCell {
	constructor(content, props) {
		this._element = null;
		this._content = content;
		if (props) {
			this._title = props.title || null;
			this._class = props.class || null;
			this._label = props.label || null;
		}
	}

	element() {
		if (!this._element) {
			this._element = document.createElement("div");
			if (this._title) {
				this._element.setAttribute("title", this._title);
			}
			if (this._class) {
				this._element.setAttribute("class", this._class);
			}
			if (this._label) {
				this._element.setAttribute("data-label", this._label);
			}
			this._element.classList.add("table-cell");
			let content = this.value("dom");
			if (content !== null) {
				if (typeof(content) === "object") {
					this._element.appendChild(content)
				}
				else {
					this._element.appendChild(document.createTextNode(content));
				}
			}
		}
		return this._element;
	}

	value(target) {
		if (target === "dom" || typeof(this._content) !== "object") {
			return this._content;
		}
		return null;
	}
}

class ITableColumn extends ITableCell {
	constructor(content, props) {
		super(content, props);
		this._name = props.name;
		this._sortable = !!props.sortable;
		this._sorted = props.sorted || null;
	}

	element() {
		if (this._element !== super.element()) {
			this._update_sorted();
		}
		return this._element;
	}

	is_sortable() {
		return this._sortable;
	}

	sort(dir) {
		if (this._sorted !== dir) {
			this._sorted = dir || null;
			if (this._element) {
				this._update_sorted();
			}
		}
	}

	sorted() {
		return this._sorted;
	}

	name() {
		return this._name;
	}

	_update_sorted() {
		if (this._sortable) {
			this._element.classList.add("sortable");
			let c_act = {
				asc: "remove",
				des: "remove"
			};
			if (this._sorted) {
				this._element.classList.add("arrows");
				if (this._sorted === "ascent") {
					c_act["asc"] = "add";
				}
				else if (this._sorted === "descent") {
					c_act["des"] = "add";
				}
			}
			else {
				this._element.classList.remove("arrows");
			}
			for (let key in c_act) {
				this._element.classList[c_act[key]]("sorted-" + key);
			}
		}
	}
}

class ModalDialog {
	constructor(params) {
		this._params   = params;
		this._element  = null;
		this._title    = null;
		this._buttons  = [];
		this._content  = null;
		this._first    = null;
		this._last     = null;
		this._result   = null;
		this._callback = null;
	}

	element() {
		if (!this._element) {
			let ovl = document.createElement("div");
			ovl.setAttribute("class", "dialog-overlay hidden");
			let dlg = document.createElement("div");
			dlg.setAttribute("class", "dialog");
			let con = document.createElement("div");
			con.setAttribute("class", "container");
			this._title = document.createElement("div");
			this._title.setAttribute("class", "title");
			{
				let tt = document.createElement("div");
				tt.setAttribute("class", "title-text");
				tt.appendChild(document.createTextNode(this._params.title || ""));
				this._title.appendChild(tt);
			}
			let that = this;
			{
				let cbt = document.createElement("button");
				cbt.setAttribute("type", "button");
				cbt.setAttribute("class", "close-btn");
				cbt.appendChild(document.createTextNode("x"));
				this._title.appendChild(cbt);
				this._buttons = [ cbt ];
				cbt.addEventListener("click", function(event) {
					that.hide();
				});
			}
			con.appendChild(this._title);
			let frm = document.createElement("form");
			this._content = document.createElement("div");
			frm.appendChild(this._content);
			let bdv = document.createElement("div");
			bdv.setAttribute("class", "dialog-buttons");
			this._add_buttons(bdv);
			frm.appendChild(bdv);
			con.appendChild(frm);
			dlg.appendChild(con);
			ovl.appendChild(dlg);
			this._element = ovl;
			this._gen_content();
			this._update_first_last();
			this._element.addEventListener("click", function(event) {
				if (event.target === this && that._params.overlay_click !== "ignore") {
					that.hide();
				}
			});
			frm.addEventListener("keydown", function(event) {
				if (event.key == "Tab") {
					if (!event.shiftKey) {
						if (event.target == that._last) {
							that._first.focus();
							event.preventDefault();
						}
					}
					else {
						if (event.target == that._first) {
							that._last.focus();
							event.preventDefault();
						}
					}
				}
			});
			frm.addEventListener("submit", function(event) {
				event.preventDefault();
				that._submit();
			});
			frm.addEventListener("reset", function(event) {
				that._reset();
			});
		}
		return this._element;
	}

	show() {
		this.element();
		this._result = null;
		this._title.querySelector("button.close-btn").classList.add("active");
		this._element.classList.remove("hidden");
		if (this._first) {
			this._first.focus();
		}

		let that = this;
		return new Promise(function(resolve, reject) {
			that._callback = resolve;
		});
	}

	hide() {
		if (this._element) {
			this._title.querySelector("button.close-btn").classList.remove("active");
			this._element.classList.add("hidden");
		}
		this._callback && this._callback(this._result);
	}

	_add_buttons(container) {
		let bl = this._params.buttons || [];
		bl.forEach(function(bt) {
			let name = null;
			let type = null;
			if (bt == "ok") {
				name = "Ok";
				type = "submit";
			}
			else if (bt == "apply") {
				name = "Apply";
				type = "submit";
			}
			else if (bt == "reset") {
				name = "Reset";
				type = "reset";
			}
			else if (bt == "login") {
				name = "Log in";
				type = "submit";
			}
			else if (bt == "cancel") {
				name = "Cancel";
				type = "close";
			}
			else if (bt == "close") {
				name = "Close";
				type = "close";
			}
			else {
				name = bt;
				type = bt;
			}
			this._add_button(container, name, type);
		}, this);
	}

	_add_button(container, text, type) {
		let btn = document.createElement("button");
		if (type == "close") {
			btn.setAttribute("type", "button");
			btn.addEventListener("click", this.hide.bind(this));
		}
		else {
			btn.setAttribute("type", type);
		}
		btn.appendChild(document.createTextNode(text));
		container.appendChild(btn);
		this._buttons.push(btn);
	}

	_gen_content() {
	}

	_update_first_last() {
		this._first = null;
		this._last  = null;
		for (const el of this._element.querySelector("form").querySelectorAll("input, select, button, a[href], [tabindex]")) {
			const ti = el.tabIndex;
			if (!isNaN(ti) && ti >= 0 && !el.disabled) {
				if (window.getComputedStyle(el, null).display !== "none") {
					if (!this._first) this._first = el;
					this._last = el;
				}
			}
		}
	}

	_submit() {
	}

	_reset() {
	}
}

class VerticalDialog extends ModalDialog {
	constructor(params) {
		super(params);
		this._inputs = null;
	}

	_insert_input_row(text, v_el) {
		if (!this._inputs) {
			this._inputs = document.createElement("div");
			this._inputs.classList.add("titled-input");
			this._content.appendChild(this._inputs);
			this._content.classList.add("vertical-content");
		}
		const l_el = document.createElement("label");
		const t_el = document.createElement("span");
		t_el.textContent = text + ": ";
		l_el.appendChild(t_el);
		l_el.appendChild(v_el);
		this._inputs.appendChild(l_el);
	}
}

class AboutDialog extends ModalDialog {
	constructor(params) {
		super({
			title:   "About",
			buttons: [ "ok" ]
		});
		this._authors = params.authors;
		this._documentation = params.documentation;
		this._source_code = params.source_code;
	}

	element() {
		if (!this._element) {
			super.element();
			this._element.children[0].classList.add("about");
			this._content.classList.add("vertical-content");
			this._content.parentElement.classList.add("vertical-content");
		}
		return this._element;
	}

	_gen_content() {
		let header = document.createElement("h2");
		header.appendChild(document.createTextNode(Router.app_name(true)));
		this._content.appendChild(header);

		let cblock = document.createElement("div");
		this._authors.forEach(function(author) {
			let ablock = document.createElement("div");
			ablock.appendChild(document.createTextNode("Copyright © " + author.years + ", "));
			cblock.appendChild(ablock);
			let alink = document.createElement("a");
			alink.setAttribute("href", author.url);
			alink.setAttribute("title", "The author's page");
			alink.setAttribute("target", "_blank");
			alink.appendChild(document.createTextNode(author.name));
			ablock.appendChild(alink);
		});
		this._content.appendChild(cblock);

		let oblock = document.createElement("div");
		oblock.setAttribute("class", "left-titled");
		let add_row = function(title, value) {
			let t_el = document.createElement("span");
			t_el.appendChild(document.createTextNode(title + ": "));
			oblock.appendChild(t_el);
			let v_el = document.createElement("div");
			value.forEach(function(v) {
				if (v_el.children.length > 0) {
					v_el.appendChild(document.createTextNode(", "));
				}
				let a_el = document.createElement("a");
				a_el.setAttribute("href", v.url);
				a_el.setAttribute("title", v.title || v.ancor);
				a_el.setAttribute("target", "_blank");
				a_el.appendChild(document.createTextNode(v.ancor));
				v_el.appendChild(a_el);
			});
			oblock.appendChild(v_el);
		};
		this._content.appendChild(oblock);
		add_row("Documentation", this._documentation);
		add_row("Source code", this._source_code);
		{
			let tl = document.createElement("span");
			tl.appendChild(document.createTextNode("PHP version: "));
			oblock.appendChild(tl);
			let vl = document.createElement("span");
			vl.appendChild(document.createTextNode(Router.php_version || "n/a"));
			oblock.appendChild(vl);
		}

		let lblock = document.createElement("div");
		lblock.appendChild(document.createTextNode(
			"This program is free software: you can redistribute it and/or modify it \
under the terms of the GNU General Public License as published by the Free \
Software Foundation, either version 3 of the License."
		));
		this._content.appendChild(lblock);
	}

	_submit() {
		this.hide();
	}
}

class ReportFilterDialog extends ModalDialog {
	constructor(params) {
		params ||= {};
		super({ title: params.title || "Filter settings", buttons: [ "apply", "reset" ] });
		this._data    = params;
		this._content = null;
		let item_list = params.item_list || [];
		this._ui_data = [
			{ name: "domain", title: "Domain" },
			{ name: "month", title: "Month" },
			{ name: "organization", title: "Organization" },
			{ name: "dkim", title: "DKIM result" },
			{ name: "spf", title: "SPF result" },
			{ name: "disposition", title: "Disposition" },
			{ name: "status", title: "Status" }
		].reduce(function(res, item) {
			if (item_list.includes(item.name))
				res.push(item);
			return res;
		}, []);
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
		for (let i = 0; i < list.length; ++i) {
			list[i].disabled = !enable;
		}
		this._update_first_last();
		if (this._first) this._first.focus();
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
	}
}

class Multiselect extends HTMLElement {
	constructor() {
		super();
		this._items    = [];
		this._aitems   = [];
		this._values   = new Set();
		this._input    = null;
		this._tags     = null;
		this._more     = null;
		this._search   = null;
		this._select   = null;
		this._listEl   = null;
		this._active   = false;
		this._disabled = false;
		this._focused  = { index: -1, item: null };
	}

	connectedCallback() {
		this._makeInputElement();
		this._makeSelectButton();
		const iw = document.createElement("div");
		iw.classList.add("multiselect-wrapper");
		this.appendChild(iw).append(this._input, this._select);
		this.addEventListener("focusin", event => {
			const rt = event.relatedTarget;
			if (event.target === this._search) {
				this.activate();
			}
		});
		this.addEventListener("focusout", event => {
			if (!this.contains(event.relatedTarget)) {
				this.deactivate();
			}
		});
		this.tabIndex = -1;
		this._disableChanged();
		this._activateSearch();
	}

	static get observedAttributes() {
		return [ "placeholder", "disabled" ];
	}

	attributeChangedCallback(name, oldValue, newValue) {
		switch (name) {
			case "placeholder":
				if (this._search) this._search.placeholder = newValue;
				break;
			case "disabled":
				if (this._disabled !== (newValue !== null)) {
					this._disabled = !this._disabled;
					this._disableChanged();
				}
				break;
		}
	}

	activate() {
		if (!this._active && !this._disabled) {
			this.classList.add("active");
			this._active = true;
			this._search.classList.add("active");
			this._displayList(true);
		}
	}

	deactivate() {
		if (this._active) {
			this.classList.remove("active");
			this._active = false;
			if (this._values.size) this._search.classList.remove("active");
			this._search.blur();
			this._search.value = "";
			this._displayList(false);
		}
	}

	clear() {
		this._items = [];
		if (this._active) this._updateList();
	}

	appendItem(value, text) {
		this._items.push({ value: value, text: text || ("" + value) });
		if (this._active) this._updateList();
	}

	setValues(data) {
		this._clearResults();
		if (data.length) {
			const items = data.reduce((res, val) => {
				const item = this._items.find(it => it.value === val);
				if (item && !this._values.has(item)) res.push(item);
				return res;
			}, []);
			this._updateResult(items);
		} else if (!this._active) {
			this._activateSearch();
		}
	}

	getValues() {
		const res = [];
		for (const item of this._values) {
			res.push(item.value);
		}
		return res;
	}

	isEmpty() {
		return !this._values.size;
	}

	get disabled() {
		return this._disabled;
	}

	set disabled(val) {
		if (val) {
			this.setAttribute("disabled", "");
		} else {
			this.removeAttribute("disabled");
		}
	}

	_makeInputElement() {
		this._input = document.createElement("div");
		this._input.classList.add("multiselect-input");
		this._tags = document.createElement("div");
		this._tags.classList.add("multiselect-tags");
		this._makeSearchBar()
		this._input.append(this._tags, this._search);
	}

	_makeSearchBar() {
		this._search = document.createElement("input");
		this._search.type = "text";
		this._search.tabIndex = 0;
		this._search.disabled = this._disabled;
		this._search.classList.add("multiselect-search");
		this._search.setAttribute("spellcheck", "false");
		this._search.placeholder = this.getAttribute("placeholder") || ""
		this._search.addEventListener("input", event => {
			this._updateList();
		});
		this._search.addEventListener("keydown", event => {
			let idx = this._focused.index;
			switch (event.code) {
				case "ArrowDown":
					event.preventDefault();
					if (++idx < this._aitems.length) this._focusItem(idx, true);
					break;
				case "ArrowUp":
					event.preventDefault();
					if (--idx >= 0) this._focusItem(idx, true);
					break;
				case "Enter":
				case "NumpadEnter":
					if (this._active) {
						event.preventDefault();
						if (this._focused.item) {
							this._updateResult(this._focused.item);
							this.deactivate();
						}
					}
					break;
				case "Escape":
					if (this._active) {
						event.preventDefault();
						event.stopPropagation();
						this.deactivate();
					}
					break;
			}
		});
	}

	_makeSelectButton() {
		this._select = document.createElement("div");
		this._select.tabIndex = -1;
		this._select.classList.add("multiselect-select");
		this._select.addEventListener("click", event => {
			if (this._active) {
				event.preventDefault();
				this.deactivate();
			}
		});
	}

	_displayList(visible) {
		if (!visible) {
			this._listEl && this._listEl.classList.add("hidden");
			return;
		}

		if (!this._listEl) {
			this._listEl = document.createElement("ul");
			this._listEl.tabIndex = -1;
			this._listEl.classList.add("multiselect-options");
			this.append(this._listEl);
			this._listEl.addEventListener("click", event => {
				if (event.target.tagName === "LI" && !event.target.classList.contains("nodata")) {
					this._updateResult(this._aitems.find(item => event.target === item.element));
					this.deactivate();
					event.preventDefault();
				}
			});
		} else {
			this._listEl.classList.remove("hidden");
			this._listEl.scrollTop = 0;
		}
		this._updateList();
	}

	_updateList() {
		while (this._listEl.firstChild) this._listEl.lastChild.remove();
		this._aitems = [];

		let cnt = 0;
		let txt = this._search.value;
		this._items.forEach(item => {
			if (!item.element) item.element = this._makeOption(item.text, this._values.has(item), false);
			if (item.text.includes(txt)) {
				this._listEl.appendChild(item.element);
				this._aitems.push(item);
				++cnt;
			}
		});

		if (cnt) {
			this._focusItem(0);
		} else {
			this._focused.index = -1;
			this._focused.item = null;
			this._listEl.append(this._makeOption("No items found", false, true));
		}
	}

	_makeTag(item) {
		const tb = document.createElement("i");
		if (!this._disabled) tb.tabIndex = 0;
		tb.addEventListener("click", event => {
			event.preventDefault();
			if (!this._disabled) this._updateResult(item)
		});
		tb.addEventListener("keydown", event => {
			if (event.code === "Space") {
				event.preventDefault();
				if (!this._disabled) this._updateResult(item);
			}
		});
		const el = document.createElement("div");
		el.classList.add("multiselect-tag");
		el.appendChild(document.createElement("span")).textContent = item.text;
		el.append(tb);
		item.tag = el;
		return el;
	}

	_makeOption(text, selected, nodata) {
		const el = document.createElement("li");
		el.textContent = text;
		if (selected) el.classList.add("selected");
		if (nodata) {
			el.classList.add("nodata");
		} else {
			el.addEventListener("pointerenter", event => this._focusItem(event.target));
		}
		return el;
	}

	_activateSearch() {
		this._search.classList[this._values.size ? "remove" : "add"]("active");
	}

	_focusItem(item, scroll) {
		let idx = -1;
		if (typeof(item) === "number") {
			idx = item;
			item = this._aitems[idx];
		} else {
			idx = this._aitems.findIndex(it => item === it.element);
			item = (idx >= 0) ? this._aitems[idx] : null;
		}
		this._focused.item && this._focused.item.element.classList.remove("focused");
		item.element.classList.add("focused");
		this._focused = { index: idx, item: item };
		if (scroll) scroll_to_element(item.element, this._listEl);
	}

	_clearResults() {
		this._values.clear();
		this._items.forEach(item => {
			item.tag && item.tag.remove();
			item.element && item.element.classList.remove("selected");
		});
		this._more && this._more.remove();
		this.dispatchEvent(new Event("change"));
	}

	_updateResult(items) {
		const MAX_ITEMS = 3;
		if (!Array.isArray(items)) items = [ items ];
		items.forEach(item => {
			if (this._values.delete(item)) {
				item.element && item.element.classList.remove("selected");
			} else {
				this._values.add(item);
				item.element && item.element.classList.add("selected");
			}
		});
		while (this._tags.firstChild) this._tags.lastChild.remove();
		let cnt = 0;
		for (const vi of this._values) {
			this._tags.append(vi.tag || this._makeTag(vi));
			if (++cnt >= MAX_ITEMS) break;
		}
		if (this._values.size > MAX_ITEMS) {
			if (!this._more) {
				this._more = document.createElement("span");
				this._more.append("and ", "", " more");
			}
			this._more.childNodes[1].textContent = this._values.size - MAX_ITEMS;
			this._tags.append(this._more);
		}
		if (!this._active) this._activateSearch();
		this.dispatchEvent(new Event("change"));
	}

	_disableChanged() {
		if (this._disabled) this.deactivate();
		for (const item of this._values) {
			if (item.tag) {
				const el = item.tag.querySelector("i");
				if (this._disabled) el.removeAttribute("tabindex"); else el.tabIndex = 0;
			}
		}
		if (this._search) this._search.disabled = this._disabled;
	}
}
customElements.define("multi-select", Multiselect);
