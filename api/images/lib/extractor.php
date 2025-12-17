<?php
declare(strict_types=1);

/**
 * ComfyUI / A1111 prompt extraction utilities
 * - Extract positive (prompt) and negative (parameters) from PNG text chunks and workflow JSON
 * - Also attempts to extract checkpoint and loras
 *
 * Public API:
 *   extract_image_metadata(string $file): array
 *     returns ['prompt' => ?string, 'parameters' => ?string, 'checkpoint' => ?string, 'loras' => string[]]
 */

// ---------- String helpers ----------
function strip_bom(string $s): string {
  return preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
}
function decode_json_deep($s): ?array {
  if (!is_string($s)) return null;
  $cur = strip_bom($s);
  for ($i = 0; $i < 2; $i++) {
    $j = json_decode($cur, true);
    if (is_array($j)) return $j;
    if (is_string($j)) {
      $t = trim(strip_bom($j));
      if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
        $cur = $t;
        continue;
      }
    }
    break;
  }
  return null;
}
function balance_unescaped_quotes(string $s): string {
  $count = preg_match_all('/(?<!\\)"/', $s);
  if ($count % 2 === 1) $s .= '"';
  return $s;
}
function tidy_neg(?string $neg): ?string {
  if (!is_string($neg)) return null;
  $neg = rtrim(trim($neg), "\"'\t\r\n ,");
  return $neg !== '' ? $neg : null;
}
function tidy_pos(?string $s): ?string {
  if (!is_string($s)) return null;
  $s = rtrim($s, " \t\r\n,");
  if (preg_match('/(?<!\\)"$/', $s) && (preg_match_all('/(?<!\\)"/', $s) % 2 === 1)) {
    $s = rtrim(substr($s, 0, -1), " \t\r\n,");
  }
  return $s !== '' ? $s : null;
}

// ---------- PNG chunk parsing ----------
function png_text_chunks(string $file): array {
  $out = [];
  $fh = @fopen($file, 'rb');
  if (!$fh) return $out;

  $sig = fread($fh, 8);
  if ($sig !== "\x89PNG\r\n\x1a\n") { fclose($fh); return $out; }

  while (!feof($fh)) {
    $lenData = fread($fh, 4);
    if (strlen($lenData) < 4) break;
    $len = unpack('Nlen', $lenData)['len'];
    if ($len < 0 || $len > 16777216) { break; } // 16MB guard
    $type = fread($fh, 4);
    $data = ($len > 0) ? fread($fh, $len) : '';
    fread($fh, 4); // CRC

    if ($type === 'tEXt') {
      $parts = explode("\x00", $data, 2);
      if (count($parts) === 2) $out[$parts[0]] = $parts[1];
    } elseif ($type === 'iTXt') {
      $pos = 0;
      $nul = strpos($data, "\x00", $pos); if ($nul === false) { continue; }
      $keyword = substr($data, 0, $nul); $pos = $nul + 1;
      if ($pos + 2 > strlen($data)) continue;
      $compFlag = ord($data[$pos]); $pos += 1;
      $compMethod = ord($data[$pos]); $pos += 1;

      $nul = strpos($data, "\x00", $pos); if ($nul === false) continue;
      $lang = substr($data, $pos, $nul - $pos); $pos = $nul + 1;

      $nul = strpos($data, "\x00", $pos); if ($nul === false) continue;
      $translated = substr($data, $pos, $nul - $pos); $pos = $nul + 1;

      $text = substr($data, $pos);
      if ($compFlag === 1) {
        if (function_exists('zlib_decode')) {
          $dec = @zlib_decode($text);
          if ($dec !== false && $dec !== null) $text = $dec;
        } else {
          $dec = @gzuncompress($text);
          if ($dec !== false) $text = $dec;
        }
      }
      $out[$keyword] = $text;
    } elseif ($type === 'zTXt') {
      $pos = 0;
      $nul = strpos($data, "\x00", $pos); if ($nul === false) continue;
      $keyword = substr($data, 0, $nul); $pos = $nul + 1;
      if ($pos >= strlen($data)) continue;
      // $compMethod = ord($data[$pos]); $pos += 1; // not used
      $pos += 1;
      $compData = substr($data, $pos);
      $text = $compData;
      if (function_exists('zlib_decode')) {
        $dec = @zlib_decode($compData);
        if ($dec !== false && $dec !== null) $text = $dec;
      } else {
        $dec = @gzuncompress($compData);
        if ($dec !== false) $text = $dec;
      }
      $out[$keyword] = $text;
    }

    if ($type === 'IEND') break;
  }
  fclose($fh);
  return $out;
}

// ---------- A1111 and workflow parsing ----------
function parse_a1111_prompt_block(string $s): array {
  $text = preg_replace('/^\s*prompt\s*:\s*/i', '', $s);
  if (!is_string($text)) $text = $s;
  $text = trim((string)$text);

  $cutRe = '/
    \n\s*(steps|sampler|cfg\s*scale|seed|size|model|vae|clip\s*skip|denoising\s*strength|
            hires\s*steps|hires\s*upscale|hires\s*upscaler|version|ensd|scheduler|refiner|tile|loras?)\s*:
    |
    ,\s*["\'](?:class_type|_meta|inputs|model|clip|sampler(?:_name)?|scheduler|seed|steps|width|height|
               batch_size|images|filename_prefix|SaveImage|KSampler|EmptyLatentImage|PreviewImage|workflow|nodes)
       ["\']\s*:
  /ix';

  if (preg_match('/\bnegative\s*(?:prompt)?\s*:\s*/i', $text)) {
    $parts = preg_split('/\bnegative\s*(?:prompt)?\s*:\s*/i', $text, 2);
    $pos = trim((string)($parts[0] ?? ''));
    $neg = trim((string)($parts[1] ?? ''));

    if ($neg !== '') {
      $cut = preg_match($cutRe, $neg, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : -1;
      if ($cut >= 0) $neg = trim(substr($neg, 0, $cut));
    }
    $pos = preg_replace('/[,\s]+$/', '', $pos) ?? $pos;

    return ['pos' => $pos !== '' ? $pos : null, 'neg' => $neg !== '' ? $neg : null];
  }

  $lower = strtolower($text);
  if (preg_match('/(?:^|[\n,;])\s*(lowres|worst quality|bad(?:[-\s]?anatomy|[-\s]?hands)|extra[-\s]?digits|missing[-\s]?fingers|watermark|signature|username|jpeg artifacts)\b/', $lower, $m, PREG_OFFSET_CAPTURE)) {
    $token = $m[1][0];
    $idxToken = strpos($lower, $token, (int)$m[1][1]);
    if ($idxToken !== false) {
      $pos = substr($text, 0, $idxToken);
      $neg = substr($text, $idxToken);

      $pos = preg_replace('/[,\s]+$/', '', $pos) ?? $pos;
      $cut = preg_match($cutRe, $neg, $mm, PREG_OFFSET_CAPTURE) ? $mm[0][1] : -1;
      if ($cut >= 0) $neg = substr($neg, 0, $cut);

      $pos = trim((string)$pos);
      $neg = trim((string)$neg);
      return ['pos' => $pos !== '' ? $pos : null, 'neg' => $neg !== '' ? $neg : null];
    }
  }

  return ['pos' => $text !== '' ? $text : null, 'neg' => null];
}

function parse_clip_texts_from_json_string(string $s): array {
  $posTexts = [];
  $negTexts = [];

  if ($s === '') return ['pos' => null, 'neg' => null];

  if (preg_match_all('/"class_type"\s*:\s*"([^"]*cliptextencode[^"]*)"/i', $s, $mm, PREG_OFFSET_CAPTURE)) {
    foreach ($mm[0] as $i => $m) {
      $start = max(0, $m[1] - 600);
      $chunk = substr($s, $start, 2000);

      if (preg_match('/"text"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/is', $chunk, $tm)) {
        $text = trim(stripcslashes($tm[1]));
        if ($text !== '') {
          $isNeg = false;
          if (preg_match('/"(?:_meta"\s*:\s*\{[^}]*"title"|"label")\s*:\s*"[^"]*negative[^"]*"/i', $chunk)) {
            $isNeg = true;
          }
          if ($isNeg) $negTexts[] = $text; else $posTexts[] = $text;
        }
      }
    }
  }

  $chooseLongest = function(array $arr): ?string {
    $best = null; $bestLen = -1;
    foreach ($arr as $s2) {
      $len = strlen($s2);
      if ($len > $bestLen) { $bestLen = $len; $best = $s2; }
    }
    return $best;
  };

  return [
    'pos' => $chooseLongest($posTexts),
    'neg' => $chooseLongest($negTexts),
  ];
}

/**
 * Extract negative prompt from ComfyUI workflow (decoded JSON).
 */
function extract_negative_from_workflow(array $wfData): ?string {
  $nodes = [];
  if (isset($wfData['nodes']) && is_array($wfData['nodes'])) {
    $nodes = $wfData['nodes'];
  } else {
    foreach ($wfData as $key => $v) {
      if (is_array($v) && isset($v['class_type'])) {
        $v['_node_id'] = (string)$key;
        $nodes[] = $v;
      } elseif (is_array($v)) {
        foreach ($v as $vv) {
          if (is_array($vv) && isset($vv['class_type'])) $nodes[] = $vv;
        }
      }
    }
  }
  if (!$nodes) return null;

  $negTexts = [];
  $allClipTexts = [];

  foreach ($nodes as $node) {
    if (!is_array($node)) continue;
    $cls = strtolower($node['class_type'] ?? '');
    if (strpos($cls, 'cliptextencode') !== false) {
      $text = $node['inputs']['text'] ?? null;
      if (is_string($text) && trim($text) !== '') {
        $title = strtolower(($node['_meta']['title'] ?? '') . ' ' . ($node['label'] ?? ''));
        $nodeId = $node['_node_id'] ?? null;
        $allClipTexts[] = [
          'text' => trim($text),
          'title' => $title,
          'node_id' => $nodeId,
          'is_negative_titled' => strpos($title, 'negative') !== false
        ];
        if (strpos($title, 'negative') !== false) {
          $negTexts[] = trim($text);
        }
      }
    }
  }

  if ($negTexts) {
    usort($negTexts, function($a, $b) {
      return strlen($b) - strlen($a);
    });
    return tidy_neg($negTexts[0]);
  }

  foreach ($nodes as $node) {
    if (!is_array($node)) continue;
    $cls = strtolower($node['class_type'] ?? '');
    if (strpos($cls, 'ksampler') !== false || strpos($cls, 'sampler') !== false) {
      $negativeRef = $node['inputs']['negative'] ?? null;
      if (is_array($negativeRef) && count($negativeRef) >= 1) {
        $negNodeId = (string)$negativeRef[0];
        foreach ($allClipTexts as $clipNode) {
          if (($clipNode['node_id'] ?? null) === $negNodeId) {
            return tidy_neg($clipNode['text']);
          }
        }
      }
    }
  }

  foreach ($allClipTexts as $clipNode) {
    if ($clipNode['is_negative_titled']) continue;
    $text = $clipNode['text'];
    $lower = strtolower($text);
    if (preg_match('/\b(worst quality|low quality|bad anatomy|bad proportions|signature|watermark|simple background|borders|lowres|jpeg artifacts)\b/', $lower)) {
      return tidy_neg($text);
    }
  }

  return null;
}

/**
 * Extract checkpoint from ComfyUI workflow (decoded JSON).
 */
function extract_checkpoint_from_workflow(array $wfData): ?string {
  // Flatten nodes from various ComfyUI workflow shapes
  $nodes = [];
  if (isset($wfData['nodes']) && is_array($wfData['nodes'])) {
    $nodes = $wfData['nodes'];
  } else {
    foreach ($wfData as $key => $v) {
      if (is_array($v) && isset($v['class_type'])) {
        $nodes[] = $v;
      } elseif (is_array($v)) {
        foreach ($v as $vv) {
          if (is_array($vv) && isset($vv['class_type'])) $nodes[] = $vv;
        }
      }
    }
  }
  if (!$nodes) return null;

  foreach ($nodes as $node) {
    if (!is_array($node)) continue;
    $cls = strtolower($node['class_type'] ?? '');

    // Typical ComfyUI nodes: "CheckpointLoaderSimple", "CheckpointLoader"
    if (strpos($cls, 'checkpoint') !== false) {
      $inputs = (isset($node['inputs']) && is_array($node['inputs'])) ? $node['inputs'] : [];

      // Common input keys that hold the checkpoint value
      foreach (['ckpt_name', 'model', 'checkpoint', 'ckpt'] as $k) {
        if (isset($inputs[$k]) && is_string($inputs[$k])) {
          $val = trim((string)$inputs[$k]);
          if ($val !== '') return $val;
        }
      }

      // Some workflows embed under a nested "model" object
      if (isset($inputs['model']) && is_array($inputs['model'])) {
        foreach (['ckpt_name', 'checkpoint'] as $k) {
          if (isset($inputs['model'][$k]) && is_string($inputs['model'][$k])) {
            $val = trim((string)$inputs['model'][$k]);
            if ($val !== '') return $val;
          }
        }
      }
    }
  }

  return null;
}

// ---------- High level helpers ----------
function get_positive_prompt_from_png(string $file): ?string {
  $chunks = png_text_chunks($file);

  $params = $chunks['parameters'] ?? $chunks['Parameters'] ?? null;
  if (is_string($params) && trim($params) !== '') {
    $pp = parse_a1111_prompt_block($params);
    if (!empty($pp['pos'])) return tidy_pos($pp['pos']);
  }

  $direct = $chunks['prompt'] ?? $chunks['Prompt'] ?? null;
  if (is_string($direct) && trim($direct) !== '') {
    $t = ltrim($direct);
    if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
      $pp = parse_clip_texts_from_json_string($t);
      if (!empty($pp['pos'])) return tidy_pos($pp['pos']);
    } else {
      $pp = parse_a1111_prompt_block($direct);
      if (!empty($pp['pos'])) return tidy_pos($pp['pos']);
    }
  }

  foreach (['workflow','workflow_json','sd-metadata','comfyui.workflow','generation_data','prompt'] as $k) {
    if (!empty($chunks[$k]) && is_string($chunks[$k])) {
      $t = ltrim($chunks[$k]);
      if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
        $pp = parse_clip_texts_from_json_string($t);
        if (!empty($pp['pos'])) return tidy_pos($pp['pos']);
      }
    }
  }
  return null;
}
function get_negative_prompt_from_png(string $file): ?string {
  $chunks = png_text_chunks($file);

  foreach ($chunks as $k => $v) {
    $lk = strtolower((string)$k);
    if (in_array($lk, ['negative', 'negative prompt', 'negative_prompt'], true)) {
      $s = is_string($v) ? trim($v) : '';
      if ($s !== '') return tidy_neg($s);
    }
  }

  $params = $chunks['parameters'] ?? $chunks['Parameters'] ?? null;
  if (is_string($params) && trim($params) !== '') {
    $pp = parse_a1111_prompt_block($params);
    if (!empty($pp['neg'])) return tidy_neg($pp['neg']);
  }

  foreach (['workflow','workflow_json','sd-metadata','comfyui.workflow','generation_data','prompt'] as $k) {
    if (!empty($chunks[$k]) && is_string($chunks[$k])) {
      $t = ltrim($chunks[$k]);
      if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
        $wf = json_decode($t, true);
        if (is_array($wf)) {
          $ex = extract_negative_from_workflow($wf);
          if ($ex) return tidy_neg($ex);
        } else {
          $pp = parse_clip_texts_from_json_string($t);
          if (!empty($pp['neg'])) return tidy_neg($pp['neg']);
        }
      }
    }
  }
  return null;
}

/**
 * Main entry: extract metadata from an image file (PNG preferred).
 */
function extract_image_metadata(string $file): array {
  $prompt = null;
  $parameters = null;
  $checkpoint = null;
  $loras = [];

  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

  if ($ext === 'png') {
    $chunks = png_text_chunks($file);

    $prompt     = get_positive_prompt_from_png($file);
    $parameters = get_negative_prompt_from_png($file);

    if (!$prompt || !$parameters || !$checkpoint) {
      $paramsBlock = $chunks['parameters'] ?? $chunks['Parameters'] ?? null;
      if (is_string($paramsBlock) && trim($paramsBlock) !== '') {
        $pp = parse_a1111_prompt_block($paramsBlock);
        if (!$prompt && !empty($pp['pos'])) $prompt = tidy_pos($pp['pos']);
        if (!$parameters && !empty($pp['neg'])) $parameters = tidy_neg($pp['neg']);
        // Try to pull checkpoint/model name from A1111 "parameters" block if present
        if (!$checkpoint && preg_match('/\b(?:model|checkpoint)\s*:\s*([^\r\n,]+)/i', $paramsBlock, $mm)) {
          $checkpoint = trim($mm[1]);
        }
      }
    }

    $wfStr = null;
    foreach (['workflow','workflow_json','sd-metadata','comfyui.workflow','generation_data','prompt'] as $k) {
      if (!empty($chunks[$k]) && is_string($chunks[$k])) {
        $t = ltrim($chunks[$k]);
        if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) { $wfStr = $t; break; }
      }
    }
    if ($wfStr) {
      $wf = json_decode($wfStr, true);
      $json = $wfStr;
      if (preg_match('/"ckpt_name"\s*:\s*"([^"]+)"/i', $json, $m) ||
          preg_match('/"model"\s*:\s*"([^"]+)"/i', $json, $m) ||
          preg_match('/"checkpoint"\s*:\s*"([^"]+)"/i', $json, $m) ||
          preg_match('/"model_name"\s*:\s*"([^"]+)"/i', $json, $m)) {
        $checkpoint = $m[1];
      }
      // Fallback: traverse decoded workflow nodes to find a checkpoint loader
      if (!$checkpoint && is_array($wf)) {
        $ck = extract_checkpoint_from_workflow($wf);
        if ($ck) $checkpoint = $ck;
      }

      if (preg_match_all('/"lora[_\s]?name"\s*:\s*"([^"]+)"/i', $json, $mm)) $loras = array_merge($loras, $mm[1]);
      if (preg_match_all('/"lora"\s*:\s*"([^"]+)"/i', $json, $mm2))     $loras = array_merge($loras, $mm2[1]);

      $pp = parse_clip_texts_from_json_string($wfStr);
      if (!$prompt && !empty($pp['pos']))      $prompt = tidy_pos($pp['pos']);
      if (!$parameters && !empty($pp['neg']))  $parameters = tidy_neg($pp['neg']);
    }

    if (is_string($prompt))     $prompt     = tidy_pos($prompt);
    if (is_string($parameters)) $parameters = tidy_neg($parameters);
  }

  $loras = array_values(array_unique(array_filter($loras)));
  return [
    'prompt'     => $prompt,
    'parameters' => $parameters,
    'checkpoint' => $checkpoint,
    'loras'      => $loras,
  ];
}
