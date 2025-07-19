<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Video</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-xl shadow-xl w-full max-w-md">
        <h2 class="text-2xl font-bold mb-4">Upload Your Video</h2>
        <form id="uploadForm" class="space-y-4" enctype="multipart/form-data">
            <input type="file" name="video" id="videoInput" accept="video/mp4" required
                class="block w-full text-sm text-gray-500" />
            <div id="dropZone"
                class="border-2 border-dashed border-gray-300 p-4 text-center rounded cursor-pointer hover:bg-gray-50">
                Drop video here or click to upload
            </div>
            <div id="loading" class="text-blue-500 hidden">Processing...</div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Upload</button>
        </form>
        <div id="videoResult" class="mt-4 hidden">
            <h3 class="text-lg font-semibold">Your processed video:</h3>
            <video id="resultVideo" controls class="w-full mt-2"></video>
            <a id="downloadLink" class="text-blue-600 underline mt-2 inline-block">Download</a>
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

            const res = await fetch("{{ route('video.upload') }}", {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            setTimeout(() => {
                const url = `/videos/result_${data.filename}`;
                resultVideo.src = url;
                downloadLink.href = url;
                resultDiv.classList.remove('hidden');
                loading.classList.add('hidden');
            }, 8000); // tạm chờ xử lý xong (có thể dùng polling tốt hơn)
        });
    </script>
</body>

</html>
