-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2025 at 02:56 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `volunteer`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_views`
--

CREATE TABLE `activity_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `view_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `certificate_generated` tinyint(1) DEFAULT 0,
  `certificate_date` date DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `user_id`, `activity_id`, `message`, `status`, `certificate_generated`, `certificate_date`, `applied_at`) VALUES
(3, 4, 9, 'Telepon: 085794345930\n\nPengalaman: ini peengalaman volunteer saya pertama kali\n\nPesan: Ingin mewujudkan Indonesia Generasi Emas', 'approved', 1, '2025-05-16', '2025-05-07 04:29:04'),
(4, 4, 7, 'Telepon: 085794345930\n\nPengalaman: baru satu kali mengikuti volunteer\n\nPesan: ingin memajukan bangsa ', 'pending', 0, NULL, '2025-05-07 09:58:41');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `owner_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(1, 3, NULL, 'Pendaftaran Terkirim', 'Pendaftaran Anda untuk kegiatan \"After-School Tutoring\" telah berhasil dikirim dan sedang menunggu persetujuan.', 'success', 'view_application.php?id=1', 0, '2025-04-28 12:47:07'),
(3, 3, NULL, 'Pendaftaran Terkirim', 'Pendaftaran Anda untuk kegiatan \"VOLUNTEER MUGAR\" telah berhasil dikirim dan sedang menunggu persetujuan.', 'success', 'view_application.php?id=2', 0, '2025-04-28 13:13:01'),
(5, 3, NULL, 'Pendaftaran Diterima', 'Selamat! Pendaftaran Anda untuk kegiatan \"VOLUNTEER MUGAR\" telah diterima.', 'success', 'view_application.php?id=2', 0, '2025-04-28 13:13:50'),
(6, 4, NULL, 'Pendaftaran Terkirim', 'Pendaftaran Anda untuk kegiatan \"Cendekia Cilik\" telah berhasil dikirim dan sedang menunggu persetujuan.', 'success', 'view_application.php?id=3', 1, '2025-05-07 04:29:04'),
(7, NULL, 5, 'Pendaftaran Baru', 'Ada pendaftar baru untuk kegiatan \"Cendekia Cilik\". Silakan tinjau pendaftaran ini.', 'info', 'view_applications.php?activity_id=9', 1, '2025-05-07 04:29:04'),
(8, 4, NULL, 'Pendaftaran Diterima', 'Selamat! Pendaftaran Anda untuk kegiatan \"Cendekia Cilik\" telah diterima.', 'success', 'view_application.php?id=3', 1, '2025-05-07 04:54:50'),
(9, 4, NULL, 'Pendaftaran Terkirim', 'Pendaftaran Anda untuk kegiatan \"Tanam Harapan\" telah berhasil dikirim dan sedang menunggu persetujuan.', 'success', 'view_application.php?id=4', 1, '2025-05-07 09:58:41'),
(10, NULL, 4, 'Pendaftaran Baru', 'Ada pendaftar baru untuk kegiatan \"Tanam Harapan\". Silakan tinjau pendaftaran ini.', 'info', 'view_applications.php?activity_id=7', 0, '2025-05-07 09:58:41');

-- --------------------------------------------------------

--
-- Table structure for table `owners`
--

CREATE TABLE `owners` (
  `owner_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `organization_description` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owners`
--

INSERT INTO `owners` (`owner_id`, `name`, `email`, `password`, `organization_name`, `organization_description`, `website`, `created_at`) VALUES
(4, 'Andi Lesmana', 'andilesmana@gmail.com', '$2y$10$iiD7HC2I/i4FMpsKS4CJYOLQyM4MR0.Ge5nzrEEVEFqOV1eKQ8s92', 'Humanist.co', 'Humanist.co adalah organisasi volunteer berbasis komunitas yang bergerak di bidang kemanusiaan, lingkungan, dan pengembangan sosial. Didirikan atas semangat untuk menumbuhkan kepedulian dan aksi nyata di tengah masyarakat, Humanist.co hadir sebagai wadah bagi individu yang ingin berbagi waktu, tenaga, dan ide demi menciptakan perubahan positif.\r\n\r\nKami percaya bahwa setiap manusia memiliki potensi untuk memberi dampak. Karena itu, kami menyelenggarakan berbagai kegiatan sosial seperti program berbagi untuk sesama, aksi tanam pohon, edukasi masyarakat, hingga respon bencana.\r\n\r\nDengan pendekatan kolaboratif, inklusif, dan berbasis nilai-nilai kemanusiaan, Humanist.co berkomitmen untuk menjadi jembatan antara niat baik dan kebutuhan nyata di lapangan. Bergabunglah bersama kami dan jadilah bagian dari gerakan kebaikan yang berkelanjutan.', '', '2025-05-04 04:29:54'),
(5, 'Dinda Lestari', 'dinda@gmail.com', '$2y$10$YS7uBB01umPQV3/UwLphS.H33ydQs1bJui2jyAWcHhXbFoWV7uVc.', 'Ginyard Community', 'Sebuah Organisasi Volunteer', '', '2025-05-04 05:38:51'),
(6, 'Deni Satya', 'denisatya@gmail.com', '$2y$10$l5rN3SGzOt5vJIp4TJsog.2lf7TejWQlBUJz94pxNqxEDR1GhYIiu', 'Larana Group', '', '', '2025-05-04 12:07:00');

-- --------------------------------------------------------

--
-- Table structure for table `search_history`
--

CREATE TABLE `search_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `search_term` varchar(255) NOT NULL,
  `search_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `search_history`
--

INSERT INTO `search_history` (`id`, `user_id`, `search_term`, `search_date`) VALUES
(1, 3, 'mugar', '2025-04-28 12:51:26'),
(2, 3, 'volunteer', '2025-04-28 12:52:07'),
(3, 3, 'VOLUNTEER', '2025-04-28 13:00:48'),
(4, 3, 'VOLUNTEER', '2025-04-28 13:09:40'),
(5, 3, 'mugar', '2025-04-28 13:09:59'),
(6, 3, 'mugar', '2025-04-28 13:10:20'),
(7, 4, 'Education', '2025-05-04 12:59:09'),
(8, 4, 'Health', '2025-05-07 04:13:58'),
(9, 4, 'Education', '2025-05-07 04:14:06'),
(10, 4, 'Human Rights', '2025-05-07 04:14:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `bio`, `skills`, `created_at`) VALUES
(1, 'John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'I am passionate about helping others and making a difference.', 'Teaching, Communication, First Aid', '2025-04-28 12:34:55'),
(2, 'Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Environmental activist looking for conservation opportunities.', 'Gardening, Public Speaking, Organization', '2025-04-28 12:34:55'),
(3, 'Ujang Kedu', 'ujangkedu@gmail.com', '$2y$10$/GHWLwa/TGKO8ippW0MxzOO5YIvPvqHMVSMdue1bQ0jDvWHQbg.Se', NULL, NULL, '2025-04-28 12:46:18'),
(4, 'Fiki Haryono', 'fikiharyono@gmaill.com', '$2y$10$77utHg84M9cXqRK1.kA4muNYDGsuSo2ed6td6.2.QCMxgI0NJ.dLy', NULL, NULL, '2025-05-04 12:14:37');

-- --------------------------------------------------------

--
-- Table structure for table `user_searches`
--

CREATE TABLE `user_searches` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `search_query` text NOT NULL,
  `search_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_activities`
--

CREATE TABLE `volunteer_activities` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `location` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `application_deadline` date NOT NULL,
  `required_skills` text DEFAULT NULL,
  `description` text NOT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `images` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteer_activities`
--

INSERT INTO `volunteer_activities` (`id`, `owner_id`, `title`, `category`, `location`, `event_date`, `application_deadline`, `required_skills`, `description`, `is_featured`, `created_at`, `images`) VALUES
(7, 4, 'Tanam Harapan', 'Lingkungan', 'Jalan Saguling Sumberhaya, Kecamatan Kawalu, Kelurahan Cilamajang, Kota Tasikmalaya', '2025-05-15', '2025-05-10', 'Untuk menjadi relawan dalam kegiatan Tanam Harapan, peserta diharapkan memiliki semangat kerja sama, kepedulian terhadap lingkungan, dan kesiapan bekerja di luar ruangan. Keterampilan dasar seperti menanam pohon, menggali tanah, menyiram tanaman, serta menjaga kebersihan area penanaman sangat dibutuhkan. Selain itu, kemampuan berkomunikasi dengan baik dan mengikuti instruksi dari koordinator lapangan akan sangat membantu kelancaran kegiatan. Tidak diperlukan keahlian khusus — yang paling penting adalah niat baik dan komitmen untuk berkontribusi.', 'Sebagai volunteer Tanam Harapan, kamu akan terlibat langsung dalam proses penanaman pohon dan penghijauan area yang telah ditentukan. Peranmu mencakup membantu persiapan lubang tanam, menanam bibit pohon, menyiram, serta memastikan tanaman tertanam dengan baik dan aman. Selain aktivitas fisik, kamu juga akan menjadi bagian dari kampanye edukasi lingkungan kepada masyarakat sekitar, menciptakan dampak positif yang berkelanjutan. Ini adalah kesempatan emas untuk membangun koneksi, belajar bersama, dan menjadi agen perubahan bagi bumi.', 1, '2025-05-04 05:01:24', 'uploads/activities/activity_6816f4a472f18.jpg'),
(8, 4, 'GIveBack Project', 'Kemanusiaan', 'Jalan Siliwangi, Kota Tasikmalaya', '2025-05-14', '2025-05-12', 'Menjadi bagian dari GiveBack Project membutuhkan jiwa empati yang tinggi, kemampuan bekerja dalam tim, serta kesiapan untuk terlibat langsung dalam kegiatan sosial di lapangan. Relawan diharapkan memiliki keterampilan komunikasi yang baik untuk berinteraksi dengan penerima bantuan, serta ketelitian saat menata, mendistribusikan, atau mencatat barang-barang donasi. Kesiapan fisik juga dibutuhkan karena sebagian kegiatan dilakukan secara aktif di luar ruangan. Tidak perlu pengalaman sebelumnya—yang utama adalah semangat untuk berbagi dan kepedulian terhadap sesama.', 'GiveBack Project adalah gerakan sosial yang bertujuan untuk menyalurkan bantuan kepada masyarakat yang membutuhkan, mulai dari paket sembako, pakaian layak pakai, hingga layanan sosial lainnya. Sebagai relawan, kamu akan berperan dalam proses pengumpulan, pengemasan, hingga pendistribusian donasi secara langsung. Lebih dari sekadar membantu, kamu juga akan menjadi jembatan harapan antara para donatur dan penerima manfaat. Ini adalah kesempatan untuk berbagi kebaikan, memperluas wawasan sosial, dan menjadi bagian dari perubahan nyata.', 1, '2025-05-04 05:07:59', 'uploads/activities/activity_6816f62f1a241.jpg'),
(9, 5, 'Cendekia Cilik', 'Pendidikan', 'Jalan Siliwangi, Kota Tasikmalaya', '2025-05-10', '2025-05-08', 'Relawan Cendekia Cilik diharapkan memiliki semangat pengabdian dan kepedulian terhadap dunia pendidikan anak. Keterampilan utama yang dibutuhkan meliputi kemampuan komunikasi yang baik, kesabaran dalam mendampingi anak-anak, serta kemampuan mengajar secara kreatif dan adaptif sesuai usia. Pengalaman dalam mengelola kelas kecil, mendongeng, membuat permainan edukatif, atau kegiatan seni seperti menggambar dan menyanyi merupakan nilai tambah. Relawan juga diharapkan mampu bekerja dalam tim, berpikir solutif, dan menunjukkan sikap positif dalam menghadapi tantangan di lapangan.', 'Cendekia Cilik adalah sebuah program edukasi sukarela yang dirancang untuk membantu anak-anak dari komunitas kurang terlayani dalam mengembangkan potensi akademik, sosial, dan karakter mereka. Melalui pendekatan belajar yang interaktif, kreatif, dan menyenangkan, relawan akan menjadi fasilitator pembelajaran yang tidak hanya mengajar, tetapi juga menginspirasi. Program ini mengusung semangat “berbagi ilmu, menyalakan masa depan” dengan fokus pada literasi dasar, numerasi, serta penguatan nilai-nilai positif seperti rasa percaya diri, empati, dan semangat kolaborasi.\r\n\r\n', 1, '2025-05-04 11:59:36', 'uploads/activities/activity_681756a8e7db7.jpg'),
(10, 5, 'Bumi Setara', 'Kemanusiaan', 'Jalan Saguling Sumberhaya, Kecamatan Kawalu, Kelurahan Cilamajang, Kota Tasikmalaya', '2025-05-20', '2025-05-18', 'Bumi Setara adalah sebuah inisiatif kolaboratif yang menghadirkan ruang edukatif dan reflektif tentang pentingnya kesetaraan, martabat, dan hak asasi manusia bagi semua kalangan. Event ini terdiri dari rangkaian lokakarya, diskusi terbuka, pameran interaktif, dan aksi kampanye kreatif yang bertujuan meningkatkan kesadaran publik tentang isu-isu diskriminasi, kebebasan berpendapat, hak perempuan, anak, dan kelompok rentan. Dengan semangat keberagaman, Bumi Setara menjadi tempat di mana suara dari berbagai latar belakang didengar, dihormati, dan diberdayakan untuk membentuk masyarakat yang lebih adil dan inklusif.', 'Untuk mendukung kelancaran dan keberhasilan event Bumi Setara, para relawan diharapkan memiliki sejumlah keterampilan penting. Keterampilan komunikasi publik sangat dibutuhkan agar relawan mampu menyampaikan pesan-pesan tentang hak asasi manusia dengan jelas, empatik, dan inklusif kepada peserta dari berbagai latar belakang. Kemampuan memfasilitasi diskusi juga menjadi nilai tambah, terutama dalam sesi dialog yang membahas isu-isu sensitif seperti diskriminasi, kesetaraan gender, dan hak kelompok rentan. Selain itu, relawan dengan kreativitas tinggi akan berperan penting dalam merancang materi kampanye visual seperti poster, video, atau instalasi edukatif yang menarik dan bermakna. Di sisi teknis, keterampilan dalam manajemen acara diperlukan untuk membantu jalannya kegiatan, mulai dari registrasi peserta, pengaturan waktu, hingga koordinasi logistik. Terakhir, pemahaman dasar tentang prinsip-prinsip hak asasi manusia akan memperkuat kontribusi relawan dalam setiap aspek kegiatan, sehingga nilai-nilai yang disuarakan dalam Bumi Setara benar-benar dapat menginspirasi dan menyentuh masyarakat luas.', 0, '2025-05-04 12:04:36', 'uploads/activities/activity_681757d4c08ee.jpg'),
(11, 6, 'Ekspedisi Biru', 'Kesejahteraan Hewan', 'Pantai Pangandaran', '2025-06-25', '2025-06-15', 'Relawan dalam Ekspedisi Biru diharapkan memiliki semangat kepedulian tinggi terhadap lingkungan laut serta kemampuan beradaptasi di medan alam terbuka. Keterampilan komunikasi menjadi penting untuk menyampaikan pesan konservasi kepada masyarakat lokal dengan cara yang ramah dan mudah dipahami. Pengetahuan dasar tentang ekologi laut atau mamalia laut seperti paus merupakan nilai tambah, namun tidak wajib, karena pelatihan akan diberikan. Kemampuan fisik yang cukup baik juga dibutuhkan untuk kegiatan lapangan seperti patroli laut, bersih-bersih pantai, dan pengamatan fauna laut. Selain itu, keterampilan bekerja dalam tim, berpikir kritis, dan mampu mengikuti protokol keselamatan sangat diutamakan agar seluruh kegiatan berjalan efektif, aman, dan berdampak nyata bagi lingkungan.', 'Ekspedisi Biru adalah sebuah program volunteer berbasis aksi nyata yang bertujuan untuk melindungi paus dan ekosistem laut melalui kegiatan edukatif, eksploratif, dan konservatif. Dalam program ini, para relawan akan terlibat langsung dalam kegiatan seperti pemantauan populasi paus, pembersihan pantai dan laut, edukasi masyarakat pesisir, serta kampanye penyadartahuan tentang pentingnya menjaga keberlangsungan kehidupan laut. Dengan semangat kolaboratif antara ilmuwan, aktivis lingkungan, dan masyarakat, Ekspedisi Biru mengajak siapa pun yang peduli untuk menjadi bagian dari gerakan besar penyelamatan lautan dan penghuninya — demi generasi mendatang dan bumi yang lebih seimbang.\r\n\r\n', 0, '2025-05-04 12:10:52', 'uploads/activities/activity_6817594c33af7.jpg'),
(14, 6, 'Peluk Nusantara', 'Bantuan Bencana', 'Indonesia', '2025-07-07', '2025-06-08', 'Para relawan Peluk Nusantara diharapkan memiliki keterampilan komunikasi yang baik, terutama dalam menyampaikan pesan dengan cara yang lembut dan penuh empati, baik kepada korban bencana maupun sesama relawan. Keterampilan dalam manajemen logistik juga penting untuk memastikan distribusi bantuan berjalan lancar dan tepat waktu. Selain itu, kemampuan untuk bekerja di bawah tekanan dan dalam kondisi darurat sangat diperlukan untuk memastikan keselamatan dan kenyamanan korban. Relawan dengan latar belakang psikologi atau konseling akan sangat membantu dalam menangani pemulihan trauma melalui pendampingan psikologis. Keterampilan dalam bekerja sama dalam tim dan adaptasi dengan situasi yang terus berubah juga menjadi kunci dalam keberhasilan event ini.', 'Peluk Nusantara adalah inisiatif relawan yang bertujuan untuk memberikan bantuan cepat, pemulihan, dan dukungan emosional bagi korban bencana alam di seluruh Indonesia. Dalam event ini, relawan akan terlibat dalam berbagai kegiatan mulai dari pendistribusian bantuan logistik, penanganan pengungsi, hingga sesi pemulihan trauma untuk membantu masyarakat yang terdampak bencana. Nama Peluk Nusantara menggambarkan rasa solidaritas dan kepedulian yang meluas dari Sabang hingga Merauke, dengan tujuan memberikan pelukan hangat bagi saudara-saudara kita yang sedang menghadapi kesulitan. Melalui aksi nyata dan empati, Peluk Nusantara berkomitmen untuk mendampingi korban bencana alam dalam proses pemulihan fisik dan mental mereka.', 0, '2025-05-04 12:13:48', 'uploads/activities/activity_681759fc96946.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_views`
--
ALTER TABLE `activity_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `activity_id` (`activity_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `activity_id` (`activity_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_owner` (`owner_id`,`is_read`);

--
-- Indexes for table `owners`
--
ALTER TABLE `owners`
  ADD PRIMARY KEY (`owner_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `search_history`
--
ALTER TABLE `search_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_searches`
--
ALTER TABLE `user_searches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `volunteer_activities`
--
ALTER TABLE `volunteer_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_views`
--
ALTER TABLE `activity_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `owners`
--
ALTER TABLE `owners`
  MODIFY `owner_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `search_history`
--
ALTER TABLE `search_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_searches`
--
ALTER TABLE `user_searches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `volunteer_activities`
--
ALTER TABLE `volunteer_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_views`
--
ALTER TABLE `activity_views`
  ADD CONSTRAINT `activity_views_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_views_ibfk_2` FOREIGN KEY (`activity_id`) REFERENCES `volunteer_activities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`activity_id`) REFERENCES `volunteer_activities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `owners` (`owner_id`) ON DELETE CASCADE;

--
-- Constraints for table `search_history`
--
ALTER TABLE `search_history`
  ADD CONSTRAINT `search_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_searches`
--
ALTER TABLE `user_searches`
  ADD CONSTRAINT `user_searches_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_activities`
--
ALTER TABLE `volunteer_activities`
  ADD CONSTRAINT `volunteer_activities_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `owners` (`owner_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
