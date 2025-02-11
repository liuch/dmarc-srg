/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2023 Aleksey Andreev (liuch)
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

class Notification {
	constructor(params) {
		this._timer = null;
		this._params = params;
		this._started = 0;
		this._element = this._create_element();
	}

	element() {
		return this._element;
	}

	_create_element() {
		let el = document.createElement("div");
		el.setAttribute("class", "notification");
		if (this._params.type === "error")
			el.classList.add("notif-error");
		else if (this._params.type === "warn")
			el.classList.add("notif-warn");
		else
			el.classList.add("notif-info");
		{
			let text = this._params.text;
			if (typeof(text) !== "object")
				text = [ text ];
			for (let i = 0; ; ) {
				el.appendChild(document.createTextNode(text[i]));
				++i;
				if (i == text.length)
					break;
				el.appendChild(document.createElement("br"));
			}
		}
		let btn = document.createElement("button");
		btn.setAttribute("type", "button");
		btn.setAttribute("class", "notif-close");
		btn.appendChild(document.createTextNode("x"));
		el.appendChild(btn);
		el.addEventListener("click", function(event) {
			if (event.target.classList.contains("notif-close"))
				this._remove();
		}.bind(this));
		el.addEventListener("mouseover", function() {
			this._hold();
		}.bind(this));
		el.addEventListener("mouseout", function() {
			this._release();
		}.bind(this));
		if (this._params.delay > 0)
			this._set_timeout();
		return el;
	}

	_set_timeout() {
		let delay = this._params.delay;
		if (this._started)
			delay -= Date.now() - this._started;
		else
			this._started = Date.now();
		if (delay > 0) {
			this._timer = setTimeout(function() {
				this._dissolve();
			}.bind(this), delay);
		}
		else
			this._dissolve();
	}

	_hold() {
		if (this._timer) {
			clearTimeout(this._timer);
			this._timer = null;
			this._element.classList.remove("invisible");
		}
	}

	_release () {
		if (this._params.delay > 0)
			this._set_timeout();
	}

	_dissolve() {
		this._element.classList.add("invisible");
		this._timer = setTimeout(function() {
			this._timer = null;
			this._remove();
		}.bind(this), 2000);
	}

	_remove() {
		if (this._timer) {
			clearTimeout(this._timer);
			this._timer = null;
		}
		this._element.remove();
		if (this._params.name)
			Notification.names.delete(this._params.name);
	}
}

Notification.add = function(params) {
	for (let key in Notification.defaults) {
		if (params[key] === undefined)
			params[key] = Notification.defaults[key];
	}
	let notif = new Notification(params);
	document.getElementById("notifications-block").appendChild(notif.element());
	if (params.name) {
		if (Notification.names.has(params.name))
			Notification.names.get(params.name)._remove();
		Notification.names.set(params.name, notif);
	}
	return notif;
}

Notification.defaults = {
	type: "info",
	delay: 5000
};

Notification.names = new Map();
