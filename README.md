# Author CMS - Transferable Website

A complete author website with content management system, built with React and PHP. Features story publishing, image galleries, analytics, and a full admin panel.

## 🎨 Features

### **Content Management**
- ✅ Story publishing with chapters and markdown support
- ✅ Image galleries with metadata and ratings (PG/X)
- ✅ Author profile management
- ✅ Social media integration (10+ platforms)
- ✅ SEO optimization with dynamic sitemaps

### **Admin Panel**
- ✅ Complete content management system
- ✅ Analytics dashboard with visitor tracking
- ✅ Comment moderation system
- ✅ User management and IP banning
- ✅ File upload system with image processing
- ✅ Bulk upload capabilities

### **Technical Features**
- ✅ Responsive design (mobile-friendly)
- ✅ Dark/light theme toggle
- ✅ Advanced analytics tracking
- ✅ Comment system with moderation
- ✅ Like/reaction system
- ✅ SEO-optimized URLs
- ✅ Dynamic sitemap generation
- ✅ Rate limiting and security features

## 📋 System Requirements

### **PHP Requirements**
- **PHP Version:** 8.0 or higher (8.1+ recommended)
- **Required PHP Extensions:**
  - `pdo_mysql` - Database connectivity
  - `json` - JSON data handling
  - `gd` or `imagick` - Image processing and thumbnails
  - `curl` - External API requests
  - `mbstring` - Multi-byte string handling
  - `openssl` - Secure connections and encryption
  - `fileinfo` - File type detection
  - `zip` - Archive handling (optional)
- **PHP Configuration:**
  - `upload_max_filesize` ≥ 10MB (for image uploads)
  - `post_max_size` ≥ 12MB
  - `max_execution_time` ≥ 30 seconds
  - `memory_limit` ≥ 128MB (256MB recommended)
  - `allow_url_fopen` = On (for external requests)

### **Database Requirements**
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Database Features:**
  - InnoDB storage engine support
  - UTF8MB4 character set support
  - Foreign key constraints
  - JSON data type support (MySQL 5.7+)
- **Recommended Settings:**
  - `max_allowed_packet` ≥ 16MB
  - `innodb_file_per_table` = ON

### **Web Server Requirements**
- **Apache 2.4+** with modules:
  - `mod_rewrite` (required for clean URLs)
  - `mod_headers` (for CORS and security headers)
  - `mod_ssl` (recommended for HTTPS)
- **OR Nginx 1.18+** with:
  - URL rewriting support
  - PHP-FPM integration
- **File Permissions:**
  - Web root: 755
  - PHP files: 644
  - Upload directory: 755 or 777
  - Config files: 600 (recommended)

### **Browser Compatibility**
- **Modern browsers:** Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Mobile browsers:** iOS Safari 14+, Chrome Mobile 90+
- **JavaScript:** ES6+ support required
- **CSS:** Grid and Flexbox support required

### **Hosting Compatibility**

#### **✅ Shared Hosting**
- **cPanel/WHM** - Fully supported
- **Plesk** - Fully supported
- **DirectAdmin** - Supported
- **Requirements:** PHP 8.0+, MySQL access, .htaccess support

#### **✅ VPS/Dedicated Servers**
- **Linux distributions:** Ubuntu 20.04+, CentOS 8+, Debian 11+
- **Control panels:** cPanel, Plesk, Webmin, or command line
- **Full root access** for optimal configuration

#### **✅ Cloud Hosting**
- **AWS:** EC2, Lightsail, Elastic Beanstalk
- **DigitalOcean:** Droplets, App Platform
- **Google Cloud:** Compute Engine, App Engine
- **Azure:** Virtual Machines, App Service
- **Cloudflare Pages** (with Workers for API)

## 🚀 Quick Deploy / Installation Guide

This is a **ready-to-deploy** website package. Follow these 5 simple steps to get it running on any domain:

### **Step 1: Create Database**
1. Log into your hosting control panel (cPanel, Plesk, etc.)
2. Create a new MySQL database
3. Note down your database credentials:
   - Database name
   - Database username
   - Database password
   - Database host (usually `localhost`)

### **Step 2: Import Database Schema**
1. Open phpMyAdmin from your hosting control panel
2. Select your new database
3. Click "Import" tab
4. Upload `unified-schema.sql` (included in this package)
5. Click "Go" to import

✅ **Result:** Database now has all required tables:
- `users` (admin login)
- `author_profile` (your information)
- `socials` (social media links)
- `stories` & `chapters` (content)
- `galleries` & `images` (artwork)
- `site_config` (settings)
- And more...

### **Step 3: Configure Database Connection**
1. Copy `api/config.example.php` to `api/config.php`
2. Edit `api/config.php` with your database details:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_db_username');
   define('DB_PASS', 'your_db_password');
   
   // IMPORTANT: Set CORS to your actual domain!
   define('CORS_ORIGINS', 'yourdomain.com');
   
   // Security settings - CHANGE THESE!
   define('JWT_SECRET', 'your-unique-secret-key-change-this');
   define('ANALYTICS_SALT', 'your-analytics-salt-change-this');
   ```

### **Step 4: Upload Website Files**
1. **If building from source:**
   ```bash
   npm run build
   ```
2. Upload **all files and folders** from the `dist/` directory to your domain's web root
3. Upload to: `public_html/` or `www/` or your domain's root folder
4. Set permissions on `api/uploads/` folder to `755` or `777`

**Required files in web root:**
```
yourdomain.com/
├── index.html              # React app entry point
├── .htaccess               # URL rewriting rules
├── assets/                 # JavaScript and CSS bundles
├── api/                    # PHP backend
│   ├── config.php          # Your database config
│   ├── admin/              # Admin endpoints
│   ├── auth/               # Authentication
│   ├── author/             # Author management
│   ├── chapters/           # Story chapters
│   ├── galleries/          # Image galleries
│   ├── images/             # Image management
│   ├── socials/            # Social media
│   ├── stories/            # Story management
│   └── uploads/            # File uploads (755/777 permissions)
├── icon/                   # Favicons
└── images/                 # Static images
```

### **Step 5: Admin Setup**
1. Visit: `https://yourdomain.com/admin`
2. Login with default credentials:
   - **Username:** `admin`
   - **Password:** `admin123`
3. **⚠️ IMMEDIATELY change password** for security
4. Configure your website through the admin panel

## 🎨 Admin Panel Setup

After logging in, configure your website through these admin panel sections:

### **1. Author Profile Setup**
**Go to: Admin Panel → Author Profile Manager**

Configure your author information:
- **Name:** Your author name
- **Bio:** Short description (e.g., "Fantasy & Sci-Fi Author")
- **Tagline:** Catchy phrase (e.g., "Worlds of adventure and magic")
- **Site Domain:** Your domain name (for SEO)
- **Profile Image:** Upload through Upload Manager → General, then paste URL
- **Background Images:** See background setup below

### **2. Background Images Setup**
**Go to: Admin Panel → Upload Manager**

1. Click **"General" tab**
2. Upload your background image (recommended: 1920x1080px or larger)
3. Click on the uploaded image to copy its URL
4. Go to **Author Profile Manager**
5. Paste the image URL into:
   - **Background Image:** Main background
   - **Background Image Light:** Light theme background (optional)
   - **Background Image Dark:** Dark theme background (optional)
6. Save profile changes

### **3. Social Media Setup**
**Go to: Admin Panel → Social Media Manager**

Add your social media links:
- **Twitter:** `https://twitter.com/yourusername`
- **Instagram:** `https://instagram.com/yourusername`
- **Facebook:** `https://facebook.com/yourpage`
- **YouTube:** `https://youtube.com/@yourchannel`
- **Discord:** `https://discord.gg/yourinvite`
- **Patreon:** `https://patreon.com/yourusername`
- **Website:** `https://yourwebsite.com`
- **GitHub:** `https://github.com/yourusername`
- **TikTok:** `https://tiktok.com/@yourusername`
- **Vimeo:** `https://vimeo.com/yourusername`

Click **Save changes** after adding your links.

### **4. Content Creation**

#### **Creating Stories:**
1. Go to **Story Manager** → **Create New Story**
2. Add title, description, genres, keywords
3. Upload cover image through Upload Manager
4. Create chapters with markdown content
5. Publish when ready

#### **Creating Galleries:**
1. Go to **Gallery Manager** → **Create New Gallery**
2. Add title, description, rating (PG/X)
3. Upload images through Upload Manager
4. Add metadata and organize images
5. Publish gallery

### **5. Admin Panel Features**
- **Author Profile Manager:** Configure your author information
- **Story Manager:** Create and manage story series
- **Gallery Manager:** Upload and organize image collections
- **Social Media Manager:** Configure social media links
- **Upload Manager:** Handle file uploads and organization
- **Analytics Manager:** View visitor statistics and engagement
- **Moderation Manager:** Approve/reject reader comments and ban IPs
- **Password Manager:** Change admin credentials

## ✅ Verification

After deployment, test these URLs:

- `https://yourdomain.com/` - Homepage (should show your author profile)
- `https://yourdomain.com/admin` - Admin panel (should show login)
- `https://yourdomain.com/galleries` - Galleries page
- `https://yourdomain.com/storytime` - Stories page

### **Success Indicators:**
- ✅ Website loads without errors
- ✅ Admin panel is accessible
- ✅ You can customize author information
- ✅ You can upload content through admin panel
- ✅ All pages show your configured content

## 🛠️ Troubleshooting

### **Database Connection Error**
**Symptoms:** "Database connection failed" or "Could not connect to database"

**Solutions:**
- Check database credentials in `api/config.php`
- Verify database exists and user has permissions
- Confirm database host is correct (usually `localhost`)
- Ensure MySQL service is running

### **404 Errors on Pages**
**Symptoms:** Pages other than homepage show "Not Found"

**Solutions:**
- Ensure `.htaccess` file was uploaded to web root
- Verify web server supports URL rewriting
- Check that `mod_rewrite` is enabled (Apache)
- For Nginx, ensure rewrite rules are configured

### **Can't Access Admin Panel**
**Symptoms:** Admin page won't load or shows errors

**Solutions:**
- Check that `api/` folder was uploaded correctly
- Verify PHP is working on your server (check PHP version)
- Ensure all PHP extensions are installed
- Check file permissions on PHP files (644)

### **File Upload Errors**
**Symptoms:** "Failed to upload file" or permission errors

**Solutions:**
- Set `api/uploads/` folder permissions to `755` or `777`
- Check PHP upload limits in hosting settings:
  - `upload_max_filesize` ≥ 10MB
  - `post_max_size` ≥ 12MB
- Verify disk space is available
- Check that `api/uploads/` directory exists

### **Network Error / Login Issues**
**Symptoms:** "Network Error" when trying to log in

**Default credentials:**
- **Username:** `admin`
- **Password:** `admin123`

**Solutions:**
- Check CORS setting in `api/config.php`:
  ```php
  // Make sure this matches your ACTUAL domain (no typos!)
  define('CORS_ORIGINS', 'yourdomain.com'); // my_website.com
  ```
- **Common CORS mistakes:**
  - `my_website.com` vs `mywebsite.com` (underscore vs no underscore)
  - `www.yourdomain.com` vs `yourdomain.com` (www vs no www)
  - Wrong protocol: `http://` vs `https://`
- **Quick test:** Temporarily set `define('CORS_ORIGINS', '*');` to confirm CORS is the issue
- **If login still fails:**
  - Re-import `unified-schema.sql`
  - Check browser console for API errors
  - Verify JWT_SECRET is set in config.php

### **Images Not Displaying**
**Symptoms:** Broken images or images won't load

**Solutions:**
- Check file permissions on uploaded images (644)
- Verify images were uploaded to `api/uploads/`
- Ensure image paths in database are correct
- Check that GD or Imagick extension is installed

### **Slow Performance**
**Symptoms:** Website loads slowly

**Solutions:**
- Enable PHP OPcache
- Optimize images before uploading
- Consider using a CDN (Cloudflare)
- Check server resources (CPU, RAM)
- Enable gzip compression

## 🔒 Security Checklist

After installation, complete these critical security steps:

### **Immediate Actions (Do First!)**
- [ ] **Change default admin password** from `admin123` to a strong password
  - Go to: Admin Panel → Password Manager
  - Use a password manager to generate a strong password
  - Minimum 12 characters with uppercase, lowercase, numbers, and symbols

### **Configuration Security**
- [ ] **Update JWT_SECRET** in `api/config.php`
  - Change from default to a unique random string
  - Use 32+ characters of random letters, numbers, and symbols
  - Example: `openssl rand -base64 32`

- [ ] **Update ANALYTICS_SALT** in `api/config.php`
  - Change from default to a unique random string
  - Different from JWT_SECRET

- [ ] **Set CORS_ORIGINS** to your actual domain
  - Change from wildcard `*` to your specific domain
  - Example: `define('CORS_ORIGINS', 'yourdomain.com');`
  - Match your exact domain (with or without www)

### **File Permissions**
- [ ] **Set secure file permissions:**
  - Config file: `chmod 600 api/config.php`
  - Upload directory: `chmod 755 api/uploads/` (or 777 if 755 doesn't work)
  - PHP files: `chmod 644 api/**/*.php`
  - Web root: `chmod 755`

### **Server Security**
- [ ] **Enable HTTPS** on your domain
  - Use Let's Encrypt for free SSL certificates
  - Redirect all HTTP traffic to HTTPS
  - Update CORS_ORIGINS to use https://

- [ ] **Disable directory listing**
  - Ensure `.htaccess` is in place
  - Add `Options -Indexes` to `.htaccess`

- [ ] **Hide PHP version**
  - Add `expose_php = Off` to php.ini

### **Regular Maintenance**
- [ ] **Keep PHP updated** to latest stable version
- [ ] **Monitor admin login attempts** via Analytics Manager
- [ ] **Regularly backup database** and uploaded files
- [ ] **Review and moderate comments** regularly
- [ ] **Check banned IPs list** for suspicious activity

### **Optional Advanced Security**
- [ ] Set up fail2ban for repeated login attempts
- [ ] Enable PHP rate limiting
- [ ] Use Cloudflare for DDoS protection
- [ ] Implement Content Security Policy (CSP) headers
- [ ] Enable database query logging for auditing

## 📦 Package Contents

```
dist/
├── unified-schema.sql          # Database schema
├── index.html                  # React app entry point
├── .htaccess                   # URL rewriting rules
├── assets/                     # JavaScript and CSS bundles
├── api/                        # PHP backend
│   ├── config.example.php      # Database config template
│   ├── bootstrap.php           # API initialization
│   ├── admin/                  # Admin endpoints
│   ├── auth/                   # Authentication
│   ├── author/                 # Author management
│   ├── chapters/               # Story chapters
│   ├── galleries/              # Image galleries
│   ├── images/                 # Image management
│   ├── socials/                # Social media
│   ├── stories/                # Story management
│   └── uploads/                # File uploads
├── icon/                       # Favicons and app icons
└── images/                     # Static images
```

## 🌟 Customization

This website is fully customizable through the admin panel - no coding required:

- **Author Information:** Name, bio, tagline, profile image
- **Branding:** Background images, color themes (light/dark)
- **Content:** Stories, chapters, galleries, images
- **Social Media:** All major platforms supported
- **SEO:** Meta descriptions, keywords, structured data
- **Analytics:** Track visitors, engagement, and content performance

## 📞 Support

This is a complete, self-contained website package. All functionality is included and ready to use.

### **Technology Stack**
- **Frontend:** React SPA with TypeScript and Tailwind CSS
- **Backend:** PHP 8+ REST API
- **Database:** MySQL/MariaDB with full schema
- **Build System:** Vite for optimized production builds

### **Development Environment**
If you need to modify the source code:
- **Node.js 18+** (for building frontend)
- **npm 8+** or **yarn 1.22+**
- **Git** (for version control)
- **Code editor** with PHP and React support

---

## 🎉 You're Ready!

This package contains everything needed for a professional author website. Simply follow the 5-step deployment process above, complete the security checklist, and you'll have a fully functional website with content management capabilities.

**Default admin credentials:** `admin` / `admin123`

**⚠️ Remember to change the password immediately!**

**Happy publishing!** 📚✨
