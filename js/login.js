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

class LoginDialog extends ModalDialog {
	constructor(params) {
		super();
		this._params = params || {};
		this._params.buttons = [ "ok", "cancel" ];
		this._params.title = "Authentication";
		this._params.overlay_click = "ignore";
		this._user   = null;
		this._pass   = null;
		this._msg_el = null;
	}

	remove() {
		if (this._element) {
			this._element.remove();
			this._element = null;
		}
	}

	_gen_content() {
		let tdiv = document.createElement("div");
		tdiv.setAttribute("class", "table");
		if (!this._params.nousername) {
			this._user = this._insert_row(tdiv, "User name", "text", "Enter your user name");
		}
		this._pass = this._insert_row(tdiv, "Password", "password", "Enter your password");
		this._msg_el = document.createElement("div");
		this._content.appendChild(tdiv);
		this._content.appendChild(this._msg_el);
		set_wait_status(this._msg_el, "Enter your credentials");
	}

	_insert_row(t_el, text, type, placeholder) {
		let row = document.createElement("div");
		row.setAttribute("class", "row");
		let spn = document.createElement("span");
		spn.setAttribute("class", "cell");
		spn.appendChild(document.createTextNode(text + ":"));
		row.appendChild(spn);
		let inp = document.createElement("input");
		inp.required = true;
		inp.setAttribute("type", type);
		inp.setAttribute("class", "cell");
		if (placeholder) {
			inp.setAttribute("placeholder", placeholder);
		}
		row.appendChild(inp);
		t_el.appendChild(row);
		return inp;
	}

	_enable_elements(enable) {
		this._buttons[0].disabled = !enable;
		let elements = this._element.querySelector("form").elements;
		for (let i = 0; i < elements.length; ++i) {
			elements[i].disabled = !enable;
		}
	}

	_submit() {
		this._enable_elements(false);
		let body = {};
		if (!this._params.nousername) {
			body.username = this._user.value;
		}
		body.password = this._pass.value;
		if (this._params.nofetch) {
			this._result = body;
			this.hide();
			return;
		}
		let that = this;
		set_wait_status(this._msg_el, "Sending credentials to the server...");
		window.fetch("login.php", {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify(body)
		}).then(function(resp) {
			if (resp.status !== 200)
				throw new Error("Failed to log in");
			return resp.json();
		}).then(function(data) {
			if (data.error_code !== undefined && data.error_code !== 0)
				throw new Error(data.message || "Login: Unknown error");
			that._result = data;
			that.hide();
			Notification.add({ type: "info", text: data.message || "Successfully!" });
		}).catch(function(err) {
			that._pass.value = "";
			console.warn(err.message);
			set_error_status(that._msg_el, err.message);
		}).finally(function() {
			that._enable_elements(true);
		});
	}
}

LoginDialog.start = function (params) {
	let login = new LoginDialog(params);
	document.getElementById("main-block").appendChild(login.element());
	login.show().then(function(d) {
		if (d) {
			Router.go();
		}
	}).catch(function(e) {
		console.error(e.message);
	}).finally(function() {
		login.remove();
		login = null;
	});
};

