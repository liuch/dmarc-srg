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

class ITable {
	constructor(params) {
		this._element = null;
		this._class = null;
		this._header = null;
		this._status = null;
		this._frames = [];
		this._columns = [];
		this._body = null;
		this._onsort = null;
		this._onclick = null;
		this._onfocus = null;
		this.column_set = 4095;
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
		if (!this._element) {
			this._element = document.createElement("div");
			if (this._class) this._element.setAttribute("class", this._class);
			this._element.classList.add("table");
			this._element.setAttribute("tabindex", -1);
			this._element.addEventListener("focus", event => {
				this._focused = true;
				this._update_focus();
			}, true);
			this._element.addEventListener("blur", event => {
				this._focused = false;
				this._update_focus();
			}, true);
			let th = this._element.appendChild(document.createElement("div"));
			th.setAttribute("class", "table-header");
			this._header = th.appendChild(document.createElement("div"));
			this._header.setAttribute("class", "table-row");
			this._header.addEventListener("click", event => {
				const col = this.get_column_by_element(event.target);
				if (col && col.is_sortable()) {
					if (this._onsort) this._onsort(col);
				}
			});
			this._fill_columns();
			this._body = this._element.appendChild(document.createElement("div"));
			this._body.setAttribute("class", "table-body");
			this._body.addEventListener("click", event => {
				let row = this._get_row_by_element(event.target);
				if (row) {
					this._set_selected_rows([ row ]);
					if (this._onclick) this._onclick(row);
				}
			});
			this._body.addEventListener("focus", event => {
				let row = this._get_row_by_element(event.target);
				if (row) {
					this._update_focused_row(row);
					if (this._onfocus) this._onfocus(row.element());
				}
			}, true);
			this._body.addEventListener("blur", event => {
				let row = this._get_row_by_element(event.target);
				if (row) row.onfocus(false);
			}, true);
			this._body.addEventListener("keydown", event => {
				let row = null;
				switch (event.code) {
					case "ArrowDown":
						row = this._get_row(this._focused_row !== null && (this._focused_row.id() + 1) || 0);
						break;
					case "ArrowUp":
						if (this._focused_row) {
							let id = this._focused_row.id();
							if (id >= 0) row = this._get_row(id - 1);
						}
						else {
							row = this._get_row(0);
						}
						break;
					case "PageUp":
						if (this._focused_row && this._frames.length > 0) {
							let c_id = this._focused_row.id();
							let f_fr = this._frames[0];
							let f_id = f_fr.first_index();
							if (c_id == f_id) break;
							let s_el = this._get_scroll_element();
							if (s_el) {
								let r_ht = this._focused_row.element().getBoundingClientRect().height;
								let s_ht = s_el.getBoundingClientRect().height;
								let n_id = Math.max(c_id - Math.floor(s_ht / r_ht) - 1, f_id);
								row = this._get_row(n_id);
							}
							else {
								row = f_fr.row(f_id);
							}
						}
						break;
					case "PageDown":
						if (this._focused_row && this._frames.length > 0) {
							let c_id = this._focused_row.id();
							let l_fr = this._frames[this._frames.length - 1];
							let l_id = l_fr.last_index();
							if (c_id == l_id) break;
							let s_el = this._get_scroll_element();
							if (s_el) {
								let r_ht = this._focused_row.element().getBoundingClientRect().height;
								let s_ht = s_el.getBoundingClientRect().height;
								let n_id = Math.min(c_id + Math.floor(s_ht / r_ht) - 1, l_id);
								row = this._get_row(n_id);
							}
							else {
								row = l_fr.row(l_id);
							}
						}
						break;
					case "Home":
						if (this._frames.length > 0) {
							let first_frame = this._frames[0];
							row = first_frame.row(first_frame.first_index());
						}
						break;
					case "End":
						if (this._frames.length > 0) {
							let last_frame = this._frames[this._frames.length - 1];
							row = last_frame.row(last_frame.last_index());
						}
						break;
					case "Enter":
					case "NumpadEnter":
						if (this._onclick && this._focused_row) this._onclick(this._focused_row);
						event.preventDefault();
						return;
				}
				if (row) {
					row.element().focus();
					this._set_selected_rows([ row ]);
					event.preventDefault();
				}
			});
			this._fill_frames();
		}
		return this._element;
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
		if (this._header) {
			const c_idx = this._columns.length - 1;
			if (this.column_set & (1 << c_idx)) this._header.appendChild(col.element());
		}
		return col;
	}

	set_columns_visible(indexes) {
		let col_set = indexes.reduce((res, idx) => {
			res += (1 << idx);
			return res;
		}, 0);
		if (this.column_set !== col_set) {
			this.column_set = col_set;
			if (this._header) this._fill_columns();
			this._frames.forEach(fr => fr.update());
		}
	}

	get_column_by_element(el) {
		el = el && el.closest("div.table-cell");
		if (!el) return null;

		let bmask = 1;
		for (const col of this._columns) {
			if ((this.column_set & bmask) && el === col.element()) return col;
			bmask <<= 1;
		}
	}

	display_status(status, text) {
		if (this._status) {
			this._status.remove();
			if (!status) {
				this._status = null;
				return;
			}
		}

		this.element();
		this._status = document.createElement("div");
		this._status.classList.add("table-row", "colspanned", "noninteractive");
		const tc1 = this._status.appendChild(document.createElement("div"));
		tc1.classList.add("table-cell");
		const tc2 = this._status.appendChild(document.createElement("div"));
		tc2.classList.add("table-cell");
		tc2.textContent = "\u00A0"; // Non breaking space
		if (status === "wait") {
			set_wait_status(tc1);
		} else {
			this._body.replaceChildren();
			if (status === "nodata") {
				tc1.append(text || "No data");
			} else {
				set_error_status(tc1, text);
			}
		}
		this._status.classList.add("nodata");
		this._body.append(this._status);
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

		frame.table = this;
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
		if (this._body) this._body.replaceChildren();
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
		if (this._frames.length != 1) return;

		for (let i = 0; i < this._columns.length; ++i) {
			const col = this._columns[i];
			if (col.is_sortable() && col.name() === col_name) {
				const fr = this._frames[0];
				fr.sort(i, direction);
				if (this._body) this._body.replaceChildren(fr.element());
				return;
			}
		}
	}

	set_sorted(col_name, direction) {
		for (const col of this._columns) {
			if (!col.is_sortable()) continue;
			if (col.name() !== col_name) {
				col.sort(null);
				continue;
			}

			if (direction === "toggle") {
				switch (col.sorted()) {
					case "ascent":
						direction = "descent";
						break;
					case "descent":
						direction = "ascent";
						break;
					default:
						direction = null;
						break;
				}
			}
			col.sort(direction);
		}
	}

	_fill_columns() {
		let bmask = 1;
		this._header.replaceChildren(...this._columns.reduce((res, col) => {
			if (this.column_set & bmask) {
				res.push(col.element());
			} else {
				col.remove();
			}
			bmask <<= 1;
			return res;
		}, []));
	}

	_fill_frames() {
		this._frames.forEach(fr => this._body.append(fr.element()));
	}

	_get_row(row_id) {
		const fr = this._frames.find(fr => {
			return fr.last_index() >= row_id && fr.first_index() <= row_id;
		});
		return fr && fr.row(row_id) || null;
	}

	_get_row_by_element(el) {
		if (el) {
			const r_el = el.closest("div.table-row");
			if (r_el) {
				const id = parseInt(r_el.dataset.id);
				if (id !== NaN) return this._get_row(id);
			}
		}
		return null;
	}

	_update_focus() {
		if (this._focused)
			this._element.classList.add("focused");
		else
			this._element.classList.remove("focused");
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
		this._selected_rows.forEach(row => row.select(false));
		rows.forEach(row => row.select(true));
		this._selected_rows = rows;
	}

	_get_scroll_element() {
		let t_rect = this._element.getBoundingClientRect();
		let p_elem = this._element.parentElement;
		while (p_elem) {
			let p_rect = p_elem.getBoundingClientRect();
			if (t_rect.top < p_rect.top || t_rect.bottom > p_rect.bottom) {
				return p_elem;
			}
			p_elem = p_elem.parentElement;
		}
	}
}

class ITableFrame {
	constructor(data, pos) {
		this._pos = pos;
		this._more = !!data.more;
		let id = pos;
		this._rows = data.rows.map(function(rd) {
			if (!(rd instanceof ITableRow)) {
				rd = new ITableRow(rd);
			}
			rd.id(id++);
			return rd;
		});
		this.table = null;
	}

	count() {
		return this._rows.length;
	}

	first_index() {
		return this._pos;
	}

	last_index() {
		let cnt = this._rows.length;
		return cnt > 0 ? this._pos + cnt - 1 : null;
	}

	row(id) {
		let idx = id - this._pos;
		if (idx < 0 || idx >= this._rows.length) return null;
		return this._rows[idx];
	}

	more() {
		return this._more;
	}

	element() {
		const fr = document.createDocumentFragment();
		this._rows.forEach(row => {
			row.table = this.table;
			fr.appendChild(row.element());
		});
		return fr;
	}

	update() {
		this._rows.forEach(row => row.update());
	}

	sort(col_idx, direction) {
		let dir = (direction === "ascent" && 1) || (direction === "descent" && 2) || 0;
		if (!dir) return;

		this._rows.sort((a, b) => {
			const c1 = a.cell(col_idx);
			const c2 = b.cell(col_idx);
			return dir === 1 ? this._compare_cells(c2, c1) : this._compare_cells(c1, c2);
		});
		let id = this._pos;
		this._rows.forEach(row => row.id(id++));
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
		let row_el = this._element;
		if (!row_el) {
			row_el = document.createElement("div");
			row_el.dataset.id = this._id;
			if (this._class) row_el.setAttribute("class", this._class);
			row_el.classList.add("table-row");
			this._element = row_el;

			row_el.append(...this._get_cell_elements());
			this._update_focus();
			this._update_tabindex();
			this._update_select();
		}
		return row_el;
	}

	update() {
		if (this._element) {
			this._element.replaceChildren(...this._get_cell_elements());
		}
	}

	onfocus(flag) {
		this._focused = flag;
		if (this._element) this._update_focus();
	}

	tabindex(index) {
		if (this._tabindex !== index) {
			this._tabindex = index;
			this._update_tabindex();
		}
	}

	select(flag) {
		this._selected = flag;
		if (this._element) this._update_select();
	}

	id(new_id) {
		if (new_id !== undefined && new_id !== this._id) {
			this._id = new_id;
			if (this._element) this._element.dataset.id = new_id;
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

	_get_cell_elements() {
		const col_set = this.table && this.table.column_set || 4095;
		const res = [];
		let bmask = 1;
		for (const col of this._cells) {
			if (col_set & bmask) {
				res.push(col.element());
			} else {
				col.remove();
			}
			bmask <<= 1;
		}
		return res;
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
			if (this._title) this._element.title = this._title;
			if (this._class) this._element.setAttribute("class", this._class);
			if (this._label) this._element.dataset.label = this._label;
			this._element.classList.add("table-cell");
			const content = this.value("dom");
			if (content !== null) this._element.append(content);
		}
		return this._element;
	}

	remove() {
		if (this._element) {
			this._element.remove();
			this._element = null;
		}
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

class Toolbar {
	constructor(label) {
		this._label   = label;
		this._element = null;
		this._items   = [];
		this._spacer  = {}; // Just a unique value
	}

	element() {
		if (!this._element) {
			const el = document.createElement("div");
			el.role = "toolbar";
			el.ariaLabel = this._label;
			let spacer = false
			let first  = true;
			for (const it of this._items) {
				if (it === this._spacer) {
					spacer = true;
					continue;
				}
				if (spacer) {
					it.classList.add("spacer-left");
					spacer = false;
				}
				let te = (it instanceof ToolbarButton) ? it.element() : it;
				el.append(te);
				if (first && te.tabIndex === -1) {
					first = false;
					te.tabIndex = 0;
				}
			}
			el.addEventListener("keydown", event => {
				const target = event.target;
				switch (event.code) {
					case "ArrowLeft":
						this._focusPreviousItem(event.target);
						break;
					case "ArrowRight":
						this._focusNextItem(event.target);
						break;
					case "Home":
						this._focusFirstItem();
						break;
					case "End":
						this._focusLastItem();
						break;
				}
			});
			this._element = el;
		}
		return this._element;
	}

	appendItem(item) {
		this._items.push(item);
		return this;
	}

	appendSpacer() {
		this._items.push(this._spacer);
		return this;
	}

	_focusFirstItem() {
		const first = this._element.firstElementChild;
		if (!first.hasAttribute("tabindex")) {
			this._focusNextItem(first);
			return;
		}
		this._resetTabindexValues();
		first.tabIndex = 0;
		first.focus();
	}

	_focusLastItem() {
		const last = this._element.lastElementChild;
		if (!last.hasAttribute("tabindex")) {
			this._focusPreviousItem(last);
			return;
		}
		this._resetTabindexValues();
		last.tabIndex = 0;
		last.focus();
	}

	_focusNextItem(cItem) {
		let next = cItem;
		while ((next = next.nextElementSibling)) {
			if (next.hasAttribute("tabindex")) {
				this._resetTabindexValues();
				next.tabIndex = 0;
				next.focus();
				break;
			}
		}
	}

	_focusPreviousItem(cItem) {
		let prev = cItem;
		while ((prev = prev.previousElementSibling)) {
			if (prev.hasAttribute("tabindex")) {
				this._resetTabindexValues();
				prev.tabIndex = 0;
				prev.focus();
				break;
			}
		}

	}

	_resetTabindexValues() {
		this._element.querySelectorAll('[tabindex="0"]').forEach(el => {
			el.tabIndex = -1;
		});
	}
}

class ToolbarButton {
	constructor(params) {
		this._element = null;
		this._title   = params.title || null;
		this._content = params.content || null;
		this._onclick = params.onclick || null;
	}

	element() {
		if (!this._element) {
			const el = document.createElement("button");
			el.type = "button";
			el.tabIndex = -1;
			let ce = null
			if (this._content) {
				ce = el.appendChild((typeof(this._content) === "string") && this._getSVG() || this._content);
			}
			if (this._title) {
				const popup = document.createElement("span");
				popup.classList.add("popup-label");
				el.appendChild(popup).textContent = this._title;
				if (ce && ce.nodeName.toUpperCase() === "SVG") ce.setAttribute("aria-hidden", true);
			}
			if (this._onclick) el.addEventListener("click", this._onclick);
			this._element = el;
		}
		return this._element;
	}

	_getSVG() {
		switch (this._content) {
			case "info_icon":
				return this._svgIcon(
					'0 0 16 16',
					'<path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>'
				);
			case "filter_icon":
				return this._svgIcon(
					'0 1 15 15',
					'<path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5zm1 .5v1.308l4.372 4.858A.5.5 0 0 1 7 8.5v5.306l2-.666V8.5a.5.5 0 0 1 .128-.334L13.5 3.308V2z"/>'
				);
			case "columns_icon":
				return this._svgIcon(
					'0 0 17 17',
					'<path d="M0 1.5A1.5 1.5 0 0 1 1.5 0h13A1.5 1.5 0 0 1 16 1.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5zM1.5 1a.5.5 0 0 0-.5.5v13a.5.5 0 0 0 .5.5H5V1zM10 15V1H6v14zm1 0h3.5a.5.5 0 0 0 .5-.5v-13a.5.5 0 0 0-.5-.5H11z"/>'
				);
		}
	}

	_svgIcon(view_box, html) {
		const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
		svg.setAttribute("focusable", "false");
		svg.setAttribute("fill", "currentColor");
		svg.setAttribute("viewBox", view_box);
		svg.innerHTML = html;
		return svg;
	}
}

class ModalDialog {
	constructor(params) {
		this._params   = params;
		this._element  = null;
		this._title    = null;
		this._messages = null;
		this._alert    = null;
		this._wait     = null;
		this._buttons  = [];
		this._content  = null;
		this._result   = null;
		this._callback = null;
	}

	element() {
		if (!this._element) {
			const ovl = document.createElement("div");
			ovl.classList.add("dialog-overlay", "hidden");
			this._element = ovl;
			const dlg = ovl.appendChild(document.createElement("div"));
			dlg.role = "dialog";
			dlg.ariaModal = true;
			dlg.tabIndex = -1; // To catch keydown events
			dlg.classList.add("dialog");
			const con = dlg.appendChild(document.createElement("div"));
			con.classList.add("container");
			this._title = con.appendChild(document.createElement("div"));
			this._title.classList.add("title");
			{
				const tt = this._title.appendChild(document.createElement("div"));
				tt.classList.add("title-text");
				tt.textContent = this._params.title || "";
				if (this._params.title) dlg.setAriaLabelledBy(tt);
			}
			{
				const cbt = this._title.appendChild(document.createElement("button"));
				cbt.type = "button";
				cbt.ariaLabel = "Close";
				cbt.classList.add("close-btn");
				cbt.textContent = "x";
				this._buttons = [ cbt ];
				cbt.addEventListener("click", event => this.hide());
			}
			const frm = con.appendChild(document.createElement("form"));
			frm.classList.add("vertical-content");
			this._content = frm.appendChild(document.createElement("div"));
			const bdv = frm.appendChild(document.createElement("div"));
			bdv.classList.add("dialog-buttons");
			this._add_buttons(bdv);
			this._gen_content();
			this._element.addEventListener("click", event => {
				if (event.target === event.currentTarget && this._params.overlay_click !== "ignore") {
					this.hide();
				}
			});
			this._element.addEventListener("keydown", event => {
				switch (event.code) {
					case "Tab":
						{
							const els = this._get_focusable_elements();
							const lfe = [ els[0], els[els.length - 1] ];
							switch (lfe.indexOf(event.target)) {
								case -1:
									return;
								case 0:
									if (!event.shiftKey) return;
									lfe[1].focus();
									break;
								case 1:
									if (event.shiftKey) return;
									lfe[0].focus();
									break;
							}
							event.preventDefault();
						}
						break;
					case "Esc":
					case "Escape":
						event.preventDefault();
						this.hide();
						break;
				}
			});
			frm.addEventListener("submit", event => {
				event.preventDefault();
				this._submit();
			});
			frm.addEventListener("reset", event => this._reset());
		}
		return this._element;
	}

	show() {
		this.element();
		this._result = null;
		this._element.classList.remove("hidden");
		this.focus();

		return new Promise((resolve, reject) => {
			this._callback = resolve;
		});
	}

	hide() {
		if (this._element) this._element.classList.add("hidden");
		this._callback && this._callback(this._result);
	}

	focus() {
		this.element();
		const els = this._get_focusable_elements(2);
		switch (els.length) {
			case 2:
				if (els[0].classList.contains("close-btn")) {
					els[1].focus();
					break;
				}
			case 1:
				els[0].focus();
				break;
			default:
				this._element.querySelector('[role="dialog"]').focus();
				break;
		}
	}

	display_status(type, text) {
		if (type && !text) {
			type == "error" && this._alert && this._alert.replaceChildren();
			type == "wait" && this._wait && this._wait.replaceChildren();
		} else {
			this._alert && this._alert.replaceChildren();
			this._wait && this._wait.replaceChildren();
		}
		if (!text) return;

		const t_el = document.createElement("p");
		t_el.textContent = text;
		if (type == "error") {
			if (!this._alert) {
				this._alert = this._make_msg_container();
				this._alert.role = "alert";
			}
			t_el.classList.add("error-message");
			this._alert.append(t_el);
		} else if (type == "wait") {
			if (!this._wait) {
				this._wait = this._make_msg_container();
			}
			t_el.classList.add("wait-message");
			this._wait.append(t_el);
		}
	}

	_make_msg_container() {
		if (!this._messages) {
			this._messages = document.createElement("div");
			const btns = this.element().querySelector("form .dialog-buttons");
			btns.parentElement.insertBefore(this._messages, btns);
		}
		return this._messages.appendChild(document.createElement("div"));
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

	_get_focusable_elements(max) {
		const res = [];
		if (!max) max = -1;
		for (const el of this._element.querySelectorAll("input, select, button, a[href], [tabindex]")) {
			const ti = el.tabIndex;
			if (!isNaN(ti) && ti >= 0 && !el.disabled) {
				if (window.getComputedStyle(el, null).display !== "none") {
					res.push(el);
					if (!(--max)) break;
				}
			}
		}
		return res;
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

		const lblock = this._content.appendChild(document.createElement("div"));
		lblock.classList.add("text");
		lblock.appendChild(document.createTextNode(
			"This program is free software: you can redistribute it and/or modify it \
under the terms of the GNU General Public License as published by the Free \
Software Foundation, either version 3 of the License."
		));
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
		this.focus();
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
		let ao = document.createElement("option");
		ao.setAttribute("value", "");
		ao.setAttribute("selected", "selected");
		ao.appendChild(document.createTextNode("Any"));
		sl.replaceChildren(ao);
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
		this._label    = null;
		this._tags     = null;
		this._more     = null;
		this._search   = null;
		this._select   = null;
		this._listBox  = null;
		this._active   = false;
		this._disabled = false;
		this._focused  = { index: -1, item: null };
	}

	connectedCallback() {
		this._makeSelectButton();
		const iw = document.createElement("div");
		iw.classList.add("multiselect-wrapper");
		this.appendChild(iw).append(this._makeInputElement(), this._select);
		this._makeListbox();

		this._select.setAriaControls(this._listBox);
		this._search.setAriaControls(this._listBox);
		this._select.setAriaLabelledBy(this._listBox);

		this.addEventListener("focusin", event => {
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
			this._select.ariaExpanded = true;
			this._search.ariaExpanded = true;
			this._search.classList.add("active");
			this._displayList(true);
		}
	}

	deactivate() {
		if (this._active) {
			this.classList.remove("active");
			this._active = false;
			this._select.ariaExpanded = false;
			this._search.ariaExpanded = false;
			if (this._values.size) this._search.classList.remove("active");
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

	setLabel(str) {
		this._label = str;
		if (this._listBox) this._listBox.ariaLabel = str;
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
		const inputEl = document.createElement("div");
		inputEl.classList.add("multiselect-input");
		this._tags = document.createElement("div");
		this._tags.ariaHidden = true;
		this._tags.classList.add("multiselect-tags");
		this._makeSearchBar()
		inputEl.append(this._tags, this._search);
		return inputEl;
	}

	_makeSearchBar() {
		this._search = document.createElement("input");
		this._search.type = "text";
		this._search.role = "textbox";
		this._search.tabIndex = 0;
		this._search.ariaHasPopup = "listbox";
		this._search.ariaExpanded = false;
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
				case "Down":
				case "ArrowDown":
					event.preventDefault();
					if (++idx < this._aitems.length) this._focusItem(idx, true);
					break;
				case "Up":
				case "ArrowUp":
					event.preventDefault();
					if (--idx >= 0) this._focusItem(idx, true);
					break;
				case "Home":
					event.preventDefault();
					if (idx > 0) this._focusItem(0, true);
					break;
				case "End":
					event.preventDefault();
					if (++idx > 0 && idx < this._aitems.length) this._focusItem(this._aitems.length - 1, true);
					break;
				case "Enter":
				case "NumpadEnter":
					if (this._active) {
						event.preventDefault();
						if (this._focused.item) this._updateResult(this._focused.item);
					}
					break;
				case "Esc":
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
		this._select.role = "button";
		this._select.tabIndex = -1;
		this._select.ariaExpanded = false;
		this._select.ariaHasPopup = "listbox";
		if (this._disabled) this._select.ariaDisabled = true;
		this._select.classList.add("multiselect-select");
		this._select.addEventListener("click", event => {
			if (this._active) {
				event.preventDefault();
				this.deactivate();
			}
		});
	}

	_makeListbox() {
		this._listBox = document.createElement("ul");
		this._listBox.role = "listbox";
		this._listBox.tabIndex = -1;
		if (this._label) this._listBox.ariaLabel = this._label;
		this._listBox.ariaMultiSelectable = true;
		this._listBox.classList.add("multiselect-options", "hidden");
		this.append(this._listBox);
		this._listBox.addEventListener("click", event => {
			if (event.target.role === "option") {
				event.preventDefault();
				const item = this._aitems.find(item => event.target === item.element);
				if (item) this._updateResult(item);
				this.deactivate();
			}
		});
	}

	_displayList(visible) {
		if (!visible) {
			this._listBox && this._listBox.classList.add("hidden");
			return;
		}

		this._listBox.classList.remove("hidden");
		this._listBox.scrollTop = 0;
		this._updateList();
	}

	_updateList() {
		this._listBox.replaceChildren();
		this._aitems = [];

		let cnt = 0;
		let txt = this._search.value;
		this._items.forEach(item => {
			if (!item.element) item.element = this._makeOption(item.text, this._values.has(item), false);
			if (item.text.includes(txt)) {
				this._listBox.appendChild(item.element);
				this._aitems.push(item);
				++cnt;
			}
		});

		if (cnt) {
			this._focusItem(0);
		} else {
			this._focused.index = -1;
			this._focused.item = null;
			this._listBox.append(this._makeOption("No items found", null, true));
		}
	}

	_makeTag(item) {
		const tb = document.createElement("i");
		tb.addEventListener("click", event => {
			event.preventDefault();
			if (!this._disabled) {
				this._updateResult(item)
				this._search.focus();
			}
		});
		const el = document.createElement("div");
		el.classList.add("multiselect-tag");
		const sp = el.appendChild(document.createElement("span"));
		sp.tabIndex = -1;
		sp.textContent = item.text
		el.append(tb);
		item.tag = el;
		return el;
	}

	_makeOption(text, selected, nodata) {
		const el = document.createElement("li");
		el.role = "option";
		el.textContent = text;
		if (nodata) {
			el.classList.add("nodata");
		} else {
			el.ariaSelected = selected;
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
		this._search.setAttribute("aria-activedescendant", item.element.getId());
		if (scroll) scroll_to_element(item.element, this._listBox);
	}

	_clearResults() {
		this._values.clear();
		this._items.forEach(item => {
			item.tag && item.tag.remove();
			if (item.element) item.element.ariaSelected = false;
		});
		this._more && this._more.remove();
		this.dispatchEvent(new Event("change"));
	}

	_updateResult(items) {
		const MAX_ITEMS = 3;
		if (!Array.isArray(items)) items = [ items ];
		items.forEach(item => {
			if (this._values.delete(item)) {
				if (item.element) item.element.ariaSelected = false;
			} else {
				this._values.add(item);
				if (item.element) item.element.ariaSelected = true;
			}
		});
		this._tags.replaceChildren();
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
		if (this._search) this._search.disabled = this._disabled;
		if (this._select) this._select.ariaDisabled = this._disabled;
	}
}
customElements.define("multi-select", Multiselect);

class HintButton {
	constructor(params) {
		this._params = params || {};
		this._element = null;
		this._content = null;
	}

	element() {
		if (!this._element) {
			const el = document.createElement("div");
			el.classList.add("hint-block");
			const bt = el.appendChild((new ToolbarButton({ content: "info_icon", title: "Details" })).element());
			bt.tabIndex = 0;
			const ct = el.appendChild(document.createElement("div"));
			ct.tabIndex = -1;
			ct.classList.add("hint-content", "hidden");
			bt.setAriaControls(ct);
			bt.addEventListener("click", event => {
				if (!this._content) {
					switch (typeof(this._params.content)) {
						case "function":
							this._content = this._params.content(this._params.data);
							break;
						case "object":
						case "string":
							this._content = this._params.content;
							break;
					}
					if (this._content) ct.append(this._content);
				}
				if (this._content) {
					ct.classList.remove("hidden");
					ct.focus();
				}
			});
			ct.addEventListener("focusout", event => {
				if (!event.relatedTarget || !ct.contains(event.relatedTarget)) {
					ct.classList.add("hidden");
				}
			});
			ct.addEventListener("keydown", event => {
				switch (event.code) {
					case "Esc":
					case "Escape":
						event.preventDefault();
						bt.focus();
						break;
				}
			});
			this._element = el;
		}
		return this._element;
	}

	reset() {
		if (this._content) {
			this._content.remove();
			this._content = null;
		}
	}
}

class MenuBar {
	constructor(element) {
		this._element = element;
		this._tbtn    = null;
		this._mbar    = null;
		this._focused = false;
	}

	static instance() {
		if (!this._instance) this._instance = new MenuBar(document.getElementById("main-menu-block"));
		return this._instance;
	}

	init() {
		this._tbtn = this._element.querySelector(".toggle-button");
		this._tggl = this._element.querySelector('#main-menu-toggle');
		this._mbar = this._element.querySelector('ul[role="menubar"]');
		this._updateMenu();

		function delayedFocus() {
			this._mbar.addEventListener("transitionend", event => this.focus(), { once: true });
		}

		this._element.addEventListener("focusin", event => {
			this._focused = true;
		});
		this._element.addEventListener("focusout", event => {
			this._focused = false;
			setTimeout(() => {
				if (!this._focused) this._tggl.checked = false;
			}, 0);
		});
		this._tbtn.addEventListener("keydown", event => {
			switch (event.key) {
				case " ":
				case "Enter":
					this._tggl.checked = !this._tggl.checked;
					if (this._tggl.checked) delayedFocus.call(this);
					break;
			}
		});
		this._tbtn.addEventListener("click", delayedFocus.bind(this));
		this._mbar.addEventListener("click", event => {
			const target = event.target;
			switch (target.role) {
				case "menuitem":
					if (target.ariaHasPopup === "true") {
						this._toggleMenu(target);
						this._focusItem(target);
					} else {
						this._tggl.checked = false;
					}
					break;
			}
		});
		this._mbar.addEventListener("keydown", this._onKeydown.bind(this));
		return this;
	}

	element(selector) {
		if (typeof(selector) !== "string") return this._element;
		return this._element.querySelector(selector);
	}

	focus() {
		const that = this;
		function findOldFocus(ul) {
			for (const li of ul.children) {
				if (window.getComputedStyle(li).display !== "none" && !li.classList.contains("disabled")) {
					let item = that._getMenuItem(li);
					if (item.tabIndex === 0) return item;
					if (item.ariaHasPopup) {
						item = findOldFocus(li.children[1]);
						if (item) return item;
					}
				}
			}
		}
		let m = findOldFocus(this._mbar);
		if (!m) m = this._getFirstMenuItem();
		this._focusItem(m);
	}

	updateCurrent() {
		this._mbar.querySelectorAll('[role="menuitem"][aria-current]').forEach(el => {
			el.ariaCurrent = null;
		});
		if (!this._focused) {
			this._mbar.querySelectorAll('[role="menuitem"][aria-expanded="true"]').forEach(el => {
				el.ariaExpanded = false;
			});
		}

		const url = new URL(document.location);
		url.search = "";
		const href = url.toString();
		for (const el of this._mbar.querySelectorAll('a[role="menuitem"]')) {
			if (el.href === href) {
				el.ariaCurrent = "page";
				const pi = this._getMenuItem(el.parentNode.parentNode.parentNode);
				if (pi) pi.ariaExpanded = true;
			}
		}
		this._mbar.tabIndex = -1;
	}

	insertItem(title, href, position) {
		const li = document.createElement("li");
		li.role = "none";
		const ae = li.appendChild(document.createElement("a"));
		ae.role = "menuitem";
		ae.tabIndex = -1;
		ae.href = href;
		ae.textContent = title;
		if (position < 0 || position >= this._mbar.children.length) {
			this._mbar.append(li);
		} else {
			this._mbar.insertBefore(li, this._mbar.children[position]);
		}
		return li;
	}

	_focusItem(item) {
		if (item && item.role !== "menuitem") item = this._getMenuItem(item);
		if (!item) return;

		this._mbar.querySelectorAll('[role="menuitem"][tabindex="0"]').forEach(el => {
			if (el !== item) el.tabIndex = -1;
		});
		item.tabIndex = 0;
		item.focus();
	}

	_focusNextItem(cItem) {
		this._focusItem(this._getNextMenuItem(cItem.parentNode));
	}

	_focusPreviousItem(cItem) {
		this._focusItem(this._getPreviousMenuItem(cItem.parentNode));
	}

	_getMenuItem(li) {
		return li.querySelector('[role="menuitem"]');
	}

	_isItemAvailable(li) {
		return (window.getComputedStyle(li).display !== "none" && !li.classList.contains("disabled"));
	}

	_getFirstMenuItem() {
		for (const li of this._mbar.children) {
			if (this._isItemAvailable(li)) return li;
		}
	}

	_getLastMenuItem() {
		let li = this._mbar.lastElementChild;
		while (li) {
			if (!this._isItemAvailable(li)) return this._getPreviousMenuItem(li);
			if (this._getMenuItem(li).ariaExpanded !== "true") return li;
			li = li.children[1].lastElementChild;
		}
	}

	_getNextMenuItem(li) {
		const ci = this._getMenuItem(li);
		if (ci.ariaExpanded === "true") {
			const first = ci.nextElementSibling.firstElementChild;
			if (this._isItemAvailable(first)) return first;
			return this._getNextMenuItem(first);
		}

		while (true) {
			let next = li.nextElementSibling;
			while (next) {
				if (this._isItemAvailable(next)) return next;
				next = next.nextElementSibling;
			}

			if (li.parentNode === this._mbar) return this._getFirstMenuItem();
			li = li.parentNode.parentNode;
		}
	}

	_getPreviousMenuItem(li) {
		let prev = li.previousElementSibling;
		while (prev) {
			if (!this._isItemAvailable(prev)) return this._getPreviousMenuItem(prev);
			if (this._getMenuItem(prev).ariaExpanded !== "true") return prev;
			prev = prev.children[1].lastElementChild;
		}
		if (li.parentNode === this._mbar) return this._getLastMenuItem();
		return li.parentNode.parentNode;
	}

	_updateMenu() {
		this._mbar.querySelectorAll('[role="menuitem"], [role="menu"]').forEach(el => {
			el.tabIndex = -1;
		});
	}

	_toggleMenu(item) {
		item.ariaExpanded = (item.ariaExpanded !== "true");
	}

	_onKeydown(event) {
		const tg = event.target;
		switch (event.key) {
			case " ":
			case "Enter":
				if (tg.ariaHasPopup === "true") this._toggleMenu(tg);
				break;
			case "Esc":
			case "Escape":
				this._tggl.checked = false;
				this._tbtn.focus();
				break;
			case "Up":
			case "ArrowUp":
				this._focusPreviousItem(tg);
				break;
			case "Down":
			case "ArrowDown":
				this._focusNextItem(tg);
				break;
			case "Home":
				this._focusItem(this._getFirstMenuItem());
				break;
			case "End":
				this._focusItem(this._getLastMenuItem());
				break;
		}
	}
}
