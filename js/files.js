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
	constructor(id) {
		this._fieldset = null;
	}

	display() {
		let cn = document.getElementById("main-block");
		remove_all_children(cn);
		this._create_local_file_uploading_element();
		cn.appendChild(this._fieldset);
	}

	update() {
		this._fieldset.disabled = Status.instance().error();
	}

	title() {
		return "Report Files";
	}

	_create_local_file_uploading_element() {
		this._fieldset = document.createElement("fieldset");
		let lg = document.createElement("legend");
		lg.appendChild(document.createTextNode("Uploading local report files"));
		this._fieldset.appendChild(lg);
		let fm = document.createElement("form");
		fm.setAttribute("enctype", "multipart/form-data");
		fm.setAttribute("method", "post");
		fm.appendChild(this._create_input_element("hidden", "cmd", "upload-report"));
		let fl = this._create_input_element("file", "report_file[]", null)
		fl.required = true;
		fl.multiple = true;
		fm.appendChild(fl);
		let dv = document.createElement("div");
		dv.appendChild(this._create_input_element("submit", null, "Upload reports"));
		dv.appendChild(this._create_input_element("reset", null, "Reset"));
		fm.appendChild(dv);
		fm.addEventListener("submit", function(event) {
			let fd = new FormData(fm);
			window.fetch("files.php", {
				method: "POST",
				headers: HTTP_HEADERS,
				credentials: "same-origin",
				body: fd
			}).then(function(resp) {
				if (resp.status !== 200) {
					throw new Error("Failed to upload a report file");
				}
				return resp.json();
			}).then(function(data) {
				if (data.error_code !== undefined && data.error_code !== 0)
					Notification.add({ text: (data.message || "Error!"), type: "error" });
				else
					Notification.add({ text: (data.message || "Uploaded successfully!"), type: "info" });
			}).catch(function(err) {
				Notification.add({ text: (err.message || "Error!"), type: "error" });
			});
			event.preventDefault();
			fm.reset();
		});
		this._fieldset.appendChild(fm);
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
}

