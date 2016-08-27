function getPath(url) {
	var a = document.createElement('a');
	a.href = url;
	return a.pathname;
}

function post(endpoint,cb) {
	var ajax;
	if (window.XMLHttpRequest) {
		ajax = new XMLHttpRequest();
	} else {
		ajax = new ActiveXObject("Microsoft.XMLHTTP");
	}

	var data = {
		action: 'wpls_push_subscribes',
		nonce: wpls_push.nonce,
		endpoint: endpoint
	}

	var post = '';
	for (index in data){
		post = post + index + '=' + data[index] + '&'
	}

	ajax.open('POST', wpls_push.ajaxurl, true);
	ajax.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	ajax.send(post);

	if (typeof cb == 'function'){
		cb.call(ajax.responseXML);
	}
}

var worker = getPath(wpls_push.worker);
var subscribe;
if ('serviceWorker' in navigator){
	window.addEventListener('load',function(){
		console.log('Supported');
		navigator.serviceWorker.register(worker).then(function(reg){
			console.log(':^',reg);
			reg.pushManager.subscribe({
				userVisibleOnly: true
			}).then(function(sub){
				console.log('endpoint',sub.endpoint);
				post(sub.endpoint);
			});
		}).catch(function(e){
			console.log(e);
		});
	});
}