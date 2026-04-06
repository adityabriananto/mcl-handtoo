var video, reqBtn, startBtn, stopBtn, ul, stream, recorder;
viewport = document.getElementById('viewport');
video = document.getElementById('video');
reqBtn = document.getElementById('request');
startBtn = document.getElementById('start');
stopBtn = document.getElementById('stop');
ul = document.getElementById('ul');
reqBtn.onclick = requestVideo;
startBtn.onclick = startRecording;
stopBtn.onclick = stopRecording;
startBtn.disabled = true;
ul.style.display = 'none';
stopBtn.disabled = true;

// Request access to the user's camera
navigator.mediaDevices.getUserMedia({ video: true })
.then((stream) => {
    video.srcObject = stream;
})
.catch((error) => {
    console.error("Error accessing the camera: ", error);
});

function requestVideo() {
  navigator.mediaDevices.getUserMedia({
        video: true,
        audio: true
    })
    .then(stm => {
        stream = stm;
        reqBtn.style.display = 'none';
        startBtn.removeAttribute('disabled');
        video.src = URL.createObjectURL(stream);
    }).catch(e => console.error(e));
}

function startRecording() {
    recorder = new MediaRecorder(stream, {
        mimeType: 'video/mp4'
    });
    recorder.start();
    stopBtn.removeAttribute('disabled');
    startBtn.disabled = true;
    startBtn.textContent = "Recording...";
    startBtn.classList.remove('bg-green-600');
    startBtn.classList.add('bg-gray-500');
}


function stopRecording() {
    recorder.ondataavailable = e => {
        ul.style.display = 'block';
        var a = document.createElement('a'),
           li = document.createElement('li');
        a.download = ['video_', (new Date() + '').slice(4, 28), '.webm'].join('');
        a.href = URL.createObjectURL(e.data);
        a.textContent = a.download;
        li.classList.add('list-disc');
        li.classList.add('list-inside');
        li.appendChild(a);
        ul.appendChild(li);

        let recordedChunks = [];
        if (e.data.size > 0) {
            recordedChunks.push(e.data);
        }
        const blob = new Blob(recordedChunks, { type: 'video/webm' });
        // Send the blob to the server
        const formData = new FormData();
        formData.append('video', blob, 'recorded-video.webm');
        formData.append('_token', $('input[type=hidden]').val());

        // Change Button
        startBtn.textContent = "Start";
        startBtn.classList.remove('bg-gray-500');
        startBtn.classList.add('bg-green-600');

        fetch('/fdcr-receiving/video', { // Replace with your server endpoint
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Upload successful:', data);
            $('input[name="video_file"]').val(data.file);
            alert('Upload Vide Blob Success!');
        })
        .catch(error => {
            console.error('Error uploading video:', error);
            alert('Upload Video Blob Failed!');
        });

    };
    recorder.stop();
    startBtn.removeAttribute('disabled');
    stopBtn.disabled = true;
}
