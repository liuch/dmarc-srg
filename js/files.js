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

class Files {
	constructor() {
		this._container   = null;
		this._fieldset1   = null;
		this._fieldset2   = null;
		this._dir_table   = null;
		this._element     = document.getElementById("main-block");
		this._fcount_info = null;
		this._fsize_info  = null;
		this._limits      = {
			upload_max_file_count: 0,
			upload_max_file_size:  0
		};
		this._directories = [];
	}

	display() {
		this._create_container();
		this._create_local_file_uploading_element();
		this._create_directory_loading_element();
		this._container.appendChild(this._fieldset1);
		this._container.appendChild(this._fieldset2);
		this._element.appendChild(this._container);
		this._fieldset1.focus();
	}

	update() {
		if (!Status.instance().error()) {
			this._fetch_data(true, true);
		}
	}

	title() {
		return "Report Files";
	}

	_create_container() {
		this._container = document.createElement("div");
		this._container.setAttribute("class", "panel-container round-border");
	}

	_create_local_file_uploading_element() {
		this._fieldset1 = document.createElement("fieldset");
		this._fieldset1.setAttribute("class", "round-border");
		this._fieldset1.disabled = true;
		let lg = document.createElement("legend");
		lg.appendChild(document.createTextNode("Uploading local report files"));
		this._fieldset1.appendChild(lg);
		let fm = document.createElement("form");
		fm.setAttribute("enctype", "multipart/form-data");
		fm.setAttribute("method", "post");
		fm.appendChild(this._create_input_element("hidden", "cmd", "upload-report"));
		let fl = this._create_input_element("file", "report_file[]", null)
		fl.required = true;
		fl.multiple = true;
		fm.appendChild(fl);
		let dv = document.createElement("div");
		dv.setAttribute("class", "buttons-block");
		let sb = this._create_button_element("submit", "Upload reports");
		sb.disabled = true;
		dv.appendChild(sb);
		dv.appendChild(this._create_button_element("reset", "Reset"));
		fm.appendChild(dv);
		let that = this;
		fl.addEventListener("change", function(event) {
			sb.disabled = !that._check_files(fl);
		});
		fm.addEventListener("reset", function(event) {
			sb.disabled = true;
			that._clear_warnings();
		});
		fm.addEventListener("submit", function(event) {
			window.fetch("files.php", {
				method: "POST",
				credentials: "same-origin",
				body: new FormData(fm)
			}).then(function(resp) {
				if (!resp.ok)
					throw new Error("Failed to upload a report file");
				return resp.json();
			}).then(function(data) {
				Common.checkResult(data);
				Notification.add({ text: (data.message || "Uploaded successfully!"), type: "info" });
			}).catch(function(err) {
				Common.displayError(err);
				Notification.add({ text: (err.message || "Error!"), type: "error" });
			});
			event.preventDefault();
			fm.reset();
		});
		this._fieldset1.appendChild(fm);
	}

	_create_directory_loading_element() {
		this._fieldset2 = document.createElement("fieldset");
		this._fieldset2.setAttribute("class", "round-border");
		this._fieldset2.disabled = true;
		let lg = document.createElement("legend");
		lg.appendChild(document.createTextNode("Loading report files from the server directory"));
		this._fieldset2.appendChild(lg);

		let fm = document.createElement("form");
		fm.setAttribute("method", "post");
		this._dir_table = new ITable({
			class:   "main-table subtable",
			onclick: function(row) {
				let userdata = row.userdata();
				let checkbox = row.element().querySelector("input");
				if (checkbox && !userdata.error) {
					userdata.checked = !userdata.checked;
					checkbox.checked = userdata.checked;
					this._update_directory_button();
				}
			}.bind(this),
			nodata_text: "No directories are configured."
		});
		[
			{ content: "", class: "cell-status" },
			{ content: "Name" },
			{ content: "Files" },
			{ content: "Location" }
		].forEach(function(col) {
			this._dir_table.add_column(col);
		}, this);
		fm.appendChild(this._dir_table.element());
		let bb = document.createElement("div");
		bb.setAttribute("class", "buttons-block");
		fm.appendChild(bb);
		let sb = this._create_button_element("submit", "Load reports");
		sb.disabled = true;
		bb.appendChild(sb);

		fm.addEventListener("submit", function(event) {
			sb.disabled = true;
			let ids = this._directories.filter(function(it) {
				return it.checked;
			}).map(function(it) {
				return it.id;
			});
			let that = this;
			window.fetch("files.php", {
				method: "POST",
				headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
				credentials: "same-origin",
				body: JSON.stringify({ cmd: "load-directory", ids: ids })
			}).then(function(resp) {
				if (!resp.ok)
					throw new Error("Failed to load report files");
				return resp.json();
			}).then(function(data) {
				if (!data.error_code) {
					Notification.add({ text: (data.message || "Loaded successfully!"), type: "info" });
				}
				if (data.other_errors) {
					that._notify_other_errors(data.other_errors);
				}
				Common.checkResult(data);
			}).catch(function(err) {
				Common.displayError(err);
				Notification.add({ text: (err.message || "Error!"), type: "error" });
			}).finally(function() {
				that._fetch_data(false, true);
			});
			event.preventDefault();
		}.bind(this));

		this._fieldset2.appendChild(fm);
	}

	_display_files_info() {
		this._fcount_info = document.createElement("div");
		this._fcount_info.setAttribute("class", "state-gray");
		let dv = document.createElement("div");
		dv.setAttribute("class", "state-text");
		dv.appendChild(
			document.createTextNode(
				"You can upload not more than " + this._limits.upload_max_file_count + " files."
			)
		);
		this._fcount_info.appendChild(dv);

		this._fsize_info = document.createElement("div");
		this._fsize_info.setAttribute("class", "state-gray");
		dv = document.createElement("div");
		dv.setAttribute("class", "state-text");
		dv.appendChild(
			document.createTextNode(
				"You can upload a file with no more than " + bytes2size(this._limits.upload_max_file_size) + "."
			)
		);
		this._fsize_info.appendChild(dv);

		dv = document.createElement("div");
		dv.setAttribute("class", "info-block");
		dv.appendChild(this._fcount_info);
		dv.appendChild(this._fsize_info);
		this._fieldset1.appendChild(dv);
	}

	_update_directory_loading_element() {
		this._dir_table.clear();
		let d = {};
		d.rows = this._directories.map(function(it) {
			let files  = it.files;
			let chkbox = false;
			it.checked = false;
			let rd = { cells: [], userdata: it };
			if (files < 0) {
				chkbox   = null;
				files    = "Error!";
				rd.class = "state-red";
				it.error = true;
			}
			rd.cells.push(new DirectoryCheckboxCell(chkbox));
			rd.cells.push({ content: it.name });
			rd.cells.push({ content: files, class: "state-text" });
			rd.cells.push({ content: it.location });
			return rd;
		});
		this._dir_table.add_frame(new ITableFrame(d, this._dir_table.last_row_index() + 1));
	}

	_update_directory_button() {
		this._fieldset2.querySelector("button[type=submit]").disabled = !this._directories.some(function(it) {
			return it.checked;
		});
	}

	_clear_warnings() {
		[ this._fcount_info, this._fsize_info ].forEach(function(el) {
			if (el) {
				el.classList.remove("state-red");
				el.classList.add("state-gray");
			}
		});
	}

	_notify_other_errors(errors) {
		let cut = null;
		let length = errors.length;
		if (length > 4) {
			cut = errors.slice(0, 3);
			cut.push("and " + (length - 3) + " more errors");
		}
		Notification.add({ text: cut || errors, type: "error" });
	}

	_set_warning(el) {
		if (el) {
			el.classList.remove("state-gray");
			el.classList.add("state-red");
		}
	}

	_check_files(fl_el) {
		this._clear_warnings();

		if (fl_el.files.length == 0) {
			return false;
		}

		let res = true;
		if (fl_el.files.length > this._limits.upload_max_file_count) {
			res = false;
			this._set_warning(this._fcount_info);
			let message = "You can only upload " + this._limits.upload_max_file_count + " files.";
			Notification.add({ type: "error", text: message, delay: 10000, name: "max-files" });
		}

		let bf_cnt = 0;
		for (let i = 0; i < fl_el.files.length; ++i) {
			if (fl_el.files[i].size > this._limits.upload_max_file_size) {
				++bf_cnt;
			}
		};
		if (bf_cnt > 0) {
			res = false;
			this._set_warning(this._fsize_info);
			Notification.add({
				type: "error",
				text: "" + bf_cnt + " file" + (bf_cnt > 1 && "s" || "") + " exceed the maximum allowed size.",
				delay: 10000,
				name: "max-size"
			});
		}

		return res;
	}

	_create_button_element(type, text) {
		let el = document.createElement("button");
		el.setAttribute("type", type);
		el.appendChild(document.createTextNode(text));
		return el;
	}

	_create_input_element(type, name, value) {
		let el = document.createElement("input");
		el.setAttribute("type", type);
		if (name)
			el.setAttribute("name", name);
		if (value)
			el.setAttribute("value", value);
		return el;
	}

	_fetch_data(files, dirs) {
		if (files) {
			this._fieldset1.disabled = true;
			this._fieldset1.insertBefore(set_wait_status(), this._fieldset1.children[0]);
		}
		if (dirs) {
			this._fieldset2.disabled = true;
			this._fieldset2.insertBefore(set_wait_status(), this._fieldset2.children[0]);
		}
		let that = this;
		window.fetch("files.php", {
			method: "GET",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to get loader data");
			return resp.json();
		}).then(function(data) {
			Common.checkResult(data);
			if (files) {
				that._limits.upload_max_file_count = data.upload_max_file_count;
				that._limits.upload_max_file_size  = data.upload_max_file_size;
				that._display_files_info();
				that._fieldset1.disabled = false;
			}
			if (dirs) {
				that._directories = data.directories || [];
				that._update_directory_loading_element();
				that._fieldset2.disabled = false;
			}
		}).catch(function(err) {
			Common.displayError(err);
			Notification.add({ type: "error", text: err.message });
			if (files) {
				that._fieldset1.insertBefore(set_error_status(null, err.message), that._fieldset1.children[0]);
			}
			if (dirs) {
				that._fieldset2.insertBefore(set_error_status(null, err.message), that._fieldset2.children[0]);
			}
		}).finally(function() {
			if (files) {
				that._fieldset1.querySelector(".wait-message").remove();
			}
			if (dirs) {
				that._fieldset2.querySelector(".wait-message").remove();
			}
		});
	}
}

class DirectoryCheckboxCell extends ITableCell {
	value(target) {
		if (target === "dom") {
			let cb = document.createElement("input");
			cb.setAttribute("type", "checkbox");
			if (this._content !== null) {
				cb.checked = this._content;
			}
			else {
				cb.disabled = true;
				cb.checked  = false;
			}
			return cb;
		}
		return this._content;
	}
}
