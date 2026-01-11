# WIP PROJECT

# PHP Image-Based Puzzle CAPTCHA

A PHP-based image puzzle CAPTCHA that **generates a puzzle** and verifies humans by asking them to identify the **X and Y coordinates** of the missing piece.

✔ Uses **Intervention Image** for image processing  
✔ Framework-agnostic (Laravel supported) 

---

## Features

- Image-based puzzle CAPTCHA
- No JavaScript required
- Uses uploaded images
- Random puzzle piece cut from image
- Coordinate-based verification (X/Y)
- Configurable tolerance & difficulty
- One-time secure tokens
- Session-based storage (default)
- Laravel integration support

---

## Requirements

- PHP **8.0+**
- GD or Imagick
- Composer
- Intervention Image

---

## Installation

Install via Composer:

```bash
composer require glennbaquero/php-image-puzzle-captcha
```


