<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Video</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-lg w-full max-w-lg mx-auto">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-6 text-center">Upload Your Video</h2>
        <form id="uploadForm" class="space-y-6" enctype="multipart/form-data">
            <input type="file" name="video" id="videoInput" accept="video/mp4" required
                class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors" />
            <div id="dropZone"
                class="border-2 border-dashed border-gray-300 p-6 sm:p-8 text-center rounded-xl cursor-pointer hover:bg-gray-100 transition-colors bg-gray-50">
                <p class="text-gray-500 text-sm sm:text-base">Drop video here or click to upload</p>
            </div>
            <div id="loading" class="text-blue-600 font-medium text-center hidden">Processing...</div>
            <button type="submit"
                class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Upload</button>
        </form>
        <div id="videoResult" class="mt-6 hidden">
            <h3 class="text-lg sm:text-xl font-semibold text-gray-800 mb-3">Your processed video:</h3>
            <video id="resultVideo" controls class="w-full rounded-lg shadow-sm mt-2"></video>
            <a id="downloadLink"
                class="inline-block mt-3 text-blue-600 font-medium underline hover:text-blue-800 transition-colors">Download</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('uploadForm');
        const videoInput = document.getElementById('videoInput');
        const dropZone = document.getElementById('dropZone');
        const loading = document.getElementById('loading');
        const resultDiv = document.getElementById('videoResult');
        const resultVideo = document.getElementById('resultVideo');
        const downloadLink = document.getElementById('downloadLink');

        // Drag and drop
        ['dragenter', 'dragover'].forEach(evt => dropZone.addEventListener(evt, e => e.preventDefault()));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            videoInput.files = e.dataTransfer.files;
        });
        dropZone.addEventListener('click', () => videoInput.click());

        form.addEventListener('submit', async e => {
            e.preventDefault();
            const file = videoInput.files[0];
            if (!file) return;

            loading.classList.remove('hidden');
            resultDiv.classList.add('hidden');

            const formData = new FormData();
            formData.append('video', file);

            let data = null;

            try {
                const res = await fetch("{{ route('video.upload') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                if (res.ok) {
                    data = await res.json();
                } else if (res.status === 422) {
                    const errorData = await res.json();
                    loading.classList.add('hidden');
                    return;
                } else {
                    const errorData = await res.text();
                    loading.classList.add('hidden');
                    return;
                }
            } catch (error) {
                loading.classList.add('hidden');
                return;
            }

            const filename = data.filename;
            const videoUrl = `/videos/result_${filename}`;

            // Polling check every 3 seconds
            const interval = setInterval(async () => {
                const check = await fetch(videoUrl, {
                    method: 'HEAD'
                });

                if (check.ok) {
                    clearInterval(interval);
                    resultVideo.src = videoUrl;
                    downloadLink.href = videoUrl;
                    resultDiv.classList.remove('hidden');
                    loading.classList.add('hidden');
                }
            }, 3000);
        });
    </script>
</body>

</html>
