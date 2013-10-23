var stateImg = document.getElementById('stateImg')

setInterval(function () {
	ajax('/api/server/state', function (data) {
		stateImg.src = data
	})
}, 500)