<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Video</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">
    <!-- Thanh menu trên cùng -->
    <nav class="bg-white shadow-md p-3 sm:p-4 flex items-center justify-between">
        <img src="{{ asset('images/poly.jpg') }}" alt="Poly Logo" class="h-8 sm:h-10 w-auto">
        <img src="{{ asset('images/cntt.jpg') }}" alt="CNTT Logo" class="h-8 sm:h-10 w-auto">
    </nav>

    <div class="container mx-auto p-3 sm:p-4 flex flex-col md:flex-row items-start justify-between gap-4 sm:gap-6">
        <!-- Form upload -->
        <div class="bg-white p-4 sm:p-6 md:p-8 rounded-2xl shadow-lg w-full">
            <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 mb-4 sm:mb-6 text-center">
                Tải video của bạn lên
            </h2>
            <p class="text-base sm:text-lg text-gray-600 mb-3 text-center">
                Đây là công cụ giúp các bạn <span class="font-semibold text-blue-600">ghép nhanh video intro,
                    outro</span> của cuộc thi
            </p>
            <p class="text-base sm:text-lg text-gray-600 mb-4 sm:mb-6 text-center">
                Các bạn sinh viên chỉ cần <span class="font-semibold text-blue-600">upload video</span> của mình lên và
                chờ. Kết quả sẽ trả về trong <span class="font-semibold text-blue-600">vài phút</span> sau khi hệ thống
                xử lý
            </p>
            <hr>
            <form id="uploadForm" class="space-y-4 sm:space-y-6 mt-10" enctype="multipart/form-data">
                <input type="file" name="video" id="videoInput" accept="video/mp4" required
                    class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 sm:file:mr-4 sm:file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors" />
                <div id="dropZone"
                    class="border-2 border-dashed border-gray-300 p-4 sm:p-6 md:p-8 text-center rounded-xl cursor-pointer hover:bg-gray-100 transition-colors bg-gray-50">
                    <p class="text-gray-500 text-sm sm:text-base">Thả video của bạn vào đây hoặc nhấp để tải lên</p>
                    <p class="text-xs sm:text-sm text-gray-600 mt-2 italic">Chỉ chấp nhận định dạng file MP4</p>
                </div>
                <div id="loading" class="text-center hidden">
                    <span class="text-blue-600 font-medium text-center">Đang xử lý...</span>
                    <p
                        class="text-sm sm:text-base text-blue-700 font-semibold text-center mt-2 sm:mt-3 bg-blue-50 py-2 px-4 rounded-lg">
                        Vui lòng không thoát khỏi trang</p>
                </div>

                <div id="errorMessage" class="mt-3 sm:mt-4 text-red-600 font-medium text-center hidden"></div>
                <button type="submit"
                    class="w-full bg-blue-600 text-white py-2 sm:py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Upload
                    video</button>
            </form>
        </div>



        <!-- Kết quả video -->
        <div id="videoResult" class="mt-4 sm:mt-6 md:mt-0 bg-white p-4 sm:p-6 md:p-8 rounded-2xl shadow-lg w-full">
            <!-- GỢI Ý NỘI DUNG VIDEO (chuyển vào đây) -->
            <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-2xl shadow mb-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-yellow-800 mb-2">🎬 Gợi ý nội dung video</h2>
                        <p class="text-gray-700 whitespace-pre-line" id="suggestionText">
                            1 ngày học của sinh viên ngành Công nghệ thông tin tại FPT Polytechnic.
                            Khung cảnh lớp học, thực hành vẽ tay và thiết kế trên máy tính, hoạt động nhóm của chúng tôi
                            #fptpolytechnic #nganhtoihoc #chuyentoike #fpoly #nganhCNTT
                        </p>
                    </div>
                    <button onclick="copySuggestion()"
                        class="ml-4 mt-1 flex-shrink-0 bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium shadow">
                        📋 Copy
                    </button>
                </div>
            </div>
            <h3 class="text-base sm:text-lg md:text-xl font-semibold text-gray-800 mb-2">Video đã xử lý của bạn:</h3>
            <!-- Video preview -->
            <video id="resultVideo" controls class="w-full rounded-lg shadow-sm mt-4"></video>
            <a id="downloadLink"
                class="inline-block mt-2 sm:mt-3 text-blue-600 font-medium underline hover:text-blue-800 transition-colors"
                download>Tải về</a>
        </div>

    </div>
    <script>
        function copySuggestion() {
            const text = document.getElementById('suggestionText').innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('📋 Đã copy nội dung gợi ý!');
            }).catch(err => {
                alert('Không thể copy nội dung: ' + err);
            });
        }
    </script>
    <script>
        const form = document.getElementById('uploadForm');
        const videoInput = document.getElementById('videoInput');
        const dropZone = document.getElementById('dropZone');
        const loading = document.getElementById('loading');
        const errorMessage = document.getElementById('errorMessage');
        const resultDiv = document.getElementById('videoResult');
        const resultVideo = document.getElementById('resultVideo');
        const downloadLink = document.getElementById('downloadLink');
        const submitButton = form.querySelector('button[type="submit"]');

        ['dragenter', 'dragover'].forEach(evt => dropZone.addEventListener(evt, e => e.preventDefault()));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            videoInput.files = e.dataTransfer.files;
        });
        dropZone.addEventListener('click', () => videoInput.click());

        form.addEventListener('submit', async e => {
            e.preventDefault();
            const file = videoInput.files[0];
            if (!file) {
                errorMessage.classList.remove('hidden');
                errorMessage.textContent = 'Please select a video file.';
                return;
            }

            console.log('File details:', {
                name: file.name,
                size: file.size,
                type: file.type
            });

            // Disable submit button
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');

            loading.classList.remove('hidden');
            resultDiv.classList.add('hidden');
            errorMessage.classList.add('hidden');

            const formData = new FormData();
            formData.append('video', file);

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

                const responseText = await res.text();
                console.log('Response:', responseText);

                if (res.ok) {
                    const data = JSON.parse(responseText);
                    const filename = data.filename;
                    const videoUrl = `/videos/result_${filename}`;

                    const maxAttempts = 400;
                    let attempts = 0;
                    const interval = setInterval(async () => {
                        if (attempts >= maxAttempts) {
                            clearInterval(interval);
                            loading.classList.add('hidden');
                            errorMessage.classList.remove('hidden');
                            errorMessage.textContent = 'Video processing timed out.';
                            // Re-enable submit button
                            submitButton.disabled = false;
                            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                            return;
                        }

                        const check = await fetch(videoUrl, {
                            method: 'HEAD'
                        });
                        if (check.ok) {
                            clearInterval(interval);
                            resultVideo.src = videoUrl;
                            downloadLink.href = videoUrl;
                            resultDiv.classList.remove('hidden');
                            loading.classList.add('hidden');
                            // Re-enable submit button
                            submitButton.disabled = false;
                            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                        }
                        attempts++;
                    }, 3000);
                } else {
                    errorMessage.classList.remove('hidden');
                    errorMessage.textContent = JSON.parse(responseText).error ||
                        'An error occurred while uploading the video.';
                    loading.classList.add('hidden');
                    // Re-enable submit button
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                errorMessage.classList.remove('hidden');
                errorMessage.textContent = 'Failed to connect to server: ' + error.message;
                loading.classList.add('hidden');
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    </script>
</body>

</html>
