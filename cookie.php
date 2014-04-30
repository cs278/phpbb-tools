<?php

/**
 * phpBB Cookie Tester
 */
header('Content-type: text/html; charset=UTF-8');
$url = (isset($_GET['url'])) ? trim($_GET['url']) : false;
$url = (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) ? $url : false;
$debug = isset($_GET['debug']);
// "Set-Cookie: phpbb3_t6o9o_u=S6ze3LBsTLTnXLRBorAwCNS8ZPaoaafdLUhG7c8wMrc.; expires=Wed, 26-May-2010 17:30:50 GMT; path=/; domain=phpbb.cs278.org; HttpOnly"

function rate_php_version($version)
{
	return (version_compare($version, '5.2.6', '>=')) ? (version_compare($version, '5.2.7', '<>') ? true : false) : (version_compare($version, '5.1', '<') ? false : null);
}

function result_to_css_class($result)
{
	return ($result ? 'good' : ($result === null ? 'notice' : 'bad'));
}

function phpbb_version($url)
{
	if ($changelog = @file_get_contents($url . 'docs/INSTALL.html'))
	{
		preg_match('#phpBB-3\.0\.[0-9]+_to_3\.0\.([0-9]+)\.zip/tar\.gz#m', $changelog, $m);

		return '3.0.' . $m[1];
	}
	else
	{
		return 'Unable to detect';
	}
}


class Cookie
{
	private $name;
	private $value;
	private $expire;
	private $path;
	private $domain;
	private $secure;
	private $httponly;

	public function __construct($name, $value = '', $expires = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
	{
		$this->name		= (string) $name;
		$this->value	= (string) $value;
		$this->expire	= (int) $expires;
		$this->path		= str_replace('//', '/', (string) $path);
		$this->domain	= (string) $domain;
		$this->secure	= (bool) $secure;
		$this->httponly	= (bool) $httponly;
	}

	public function __get($var)
	{
		if (isset($this->$var))
		{
			return $this->$var;
		}
		else
		{
			return null;
		}
	}

	public function settableForDomain($test_domain)
	{
		if (empty($this->domain))
		{
			return true;
		}
/*
		else if ($this->domain[0] != '.')
		{
			return false;
		}
*/
		else if (strpos($this->domain, '..') !== false)
		{
			return false;
		}
		else if (substr_count($this->domain, '.') === 1)
		{
			return false;
		}
		else if (substr_count($this->domain, '.') === 2 && substr($this->domain, -1) == '.')
		{
			return false;
		}
		else if ($test_domain == $this->domain)
		{
			return true;
		}
		else
		{
			$test_domain = array_reverse(explode('.', $test_domain));
			$domain = array_reverse(explode('.', $this->domain));

			if (!$domain[sizeof($domain) - 1])
			{
				// Leading period
				// eg.: .example.com
				array_pop($domain);
				$period = true;
			}
			else
			{
				$period = false;
			}

			if (sizeof($domain) == 1)
			{
				// This is fucking stupid
				// eg.: .com
				return null;
			}

			// The cookie domain has more parts than the test domain
			// impossible for it to be set.
			// ie.: www.example.com (3) > example.com (2)
			if (sizeof($domain) > sizeof($test_domain))
			{
				return false;
			}
			else
			{
				$_test_domain = implode('.', array_reverse(array_slice($test_domain, 0, sizeof($domain))));
				$_domain = implode('.', array_reverse($domain));

				return ($_domain == $_test_domain);
			}
		}
	}

	public function settableForPath($test_path)
	{
		$test_path = str_replace('//', '/', $test_path);

		if ($this->path == '/' || !$this->path || $this->path == $test_path)
		{
			return true;
		}
		else
		{
			$test_path = explode('/', $test_path);
			$path = explode('/', $this->path);

			if (sizeof($path) > sizeof($test_path))
			{
				return false;
			}
			else
			{
				$_path = implode('/', array_slice($test_path, 0, sizeof($path)));

				return ($_path == $this->path);
			}
		}
	}

	private static function getParameterDefaults()
	{
		$method = new ReflectionMethod(__CLASS__, '__construct');

		$parameters = array();

		foreach (array_slice($method->getParameters(), 2) as $parameter)
		{
			$parameters[$parameter->getName()] = $parameter->getDefaultValue();
		}

		return $parameters;
	}

	public static function fromString($cookie)
	{
		list($name, $cookie) = explode('=', $cookie, 2);
		list($value, $cookie) = explode('; ', $cookie, 2);

		$params = array();

		$defaults = self::getParameterDefaults();

		foreach (explode('; ', $cookie) as $param)
		{
			if (strpos($param, '=') !== false)
			{
				list($_name, $_value) = explode('=', $param, 2);
			}
			else
			{
				$_name = $param;
				$_value = true;
			}
			$_name = strtolower($_name);

			// Special case
			if ($_name == 'expires')
			{
				$_value = strtotime($_value);
			}

			if (!isset($defaults[$_name]))
			{
				continue;
			}

			settype($_value, gettype($defaults[$_name]));

			$params[$_name] = $_value;
		}

		$params = array_merge($defaults, $params);

		return new self($name, $value, $params['expires'], $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<head>
	<title>phpBB Cookie Debugger</title>
	<style type="text/css">
	/*
	<![CDATA[
	*/
	body
	{
		font-family: sans-serif;
	}

	dt:after
	{
		content: ':';
	}

	.good
	{
		color: green;
	}

	.bad
	{
		font-weight: bold;
		color: red;
	}

	.notice
	{
		color: blue;
	}

	/*
	]]>
	*/
	</style>
</head>
<body>
<h1>phpBB Cookie Debugger</h1>
<p>This is a work in progress, <em>please keep it internal</em> for now. Thanks. :)</p>
<?php

if ($url)
{
	$url_before = $url;

	$url_bits = parse_url($url);

	if (!empty($url_bits['query']))
	{
		$url = str_replace('?' . $url_bits['query'], '', $url);
	}

	if (!empty($url_bits['fragment']))
	{
		$url = str_replace('#' . $url_bits['fragment'], '', $url);
	}

	$url = rtrim(rtrim($url, '#'), '?');

	if (isset($url_bits['path']) && preg_match('#[a-z][a-z0-9]+\.[a-z0-9]{3,}$#', $url))
	{
		// Ends with a filename
		$url = substr($url, 0, strrpos($url, '/') + 1);
	}
	else if (substr($url, -1) != '/')
	{
		$url .= '/';
	}
?>
<p>
	Testing the URL: <code><a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($url); ?></a></code>
<?php
	if ($url_before != $url)
	{
?>
	normalised from: <code><?php echo htmlspecialchars($url_before); ?></code>
<?php
	}
?>
</p>
<h2>Analysis</h2>
<dl>
	<dt>phpBB Version</dt>
	<dd><?php echo phpbb_version($url); ?></dd>
<?php
	unset($url_before);

	$handle = curl_init($url . 'index.php');

	$url = parse_url($url);
?>
	<dt>Board domain</dt>
	<dd><?php echo htmlspecialchars($url['host']); ?></dd>
	<dt>Board path</dt>
	<dd><?php echo htmlspecialchars($url['path']); ?></dd>
	<dt>Board secure</dt>
	<dd><?php echo ($url['scheme'] == 'https') ? 'Yes' : 'No'; ?></dd>
<?php

	curl_setopt_array($handle, array(
		CURLOPT_AUTOREFERER		=> true,
		CURLOPT_FOLLOWLOCATION	=> true,
		CURLOPT_MAXREDIRS		=> 4,

		CURLOPT_HEADER			=> true,
//		CURLOPT_NOBODY			=> true,

		CURLOPT_RETURNTRANSFER	=> true,


		//CURLOPT_PROTOCOLS		=> CURLPROTO_HTTP | CURLPROTO_HTTPS,

		CURLOPT_USERAGENT		=> 'phpBB Cookie Checker <http://www.cs278.org/>',
	));

	$response = array(
		'headers'	=> '',
		'body'		=> ''
	);

	list($response['headers'], $response['body']) = explode("\r\n\r\n", curl_exec($handle), 2);

	$response['headers'] = explode("\r\n", $response['headers']);

	$info = curl_getinfo($handle);
	curl_close($handle);

//	var_dump($response);
//	var_dump($info);

	$phpbb_cookie_suffixes = array(
		'_u', '_k', '_sid',
	);

	$httpd_version = false;
	$php_version = false;

	$prefixes = array();
	$cookies = array();

	foreach ($response['headers'] as $header)
	{
		if (strpos($header, 'Set-Cookie: ') === 0)
		{
			$cookies[] = $cookie = Cookie::fromString(substr($header, 12));

			foreach ($phpbb_cookie_suffixes as $suffix)
			{
				if (strrpos($cookie->name, $suffix) !== false && (strrpos($cookie->name, $suffix) + strlen($suffix) === strlen($cookie->name)))
				{
					$prefix = substr($cookie->name, 0, -strlen($suffix));

					if (isset($prefixes[$prefix]))
					{
						$prefixes[$prefix]++;
					}
					else
					{
						$prefixes[$prefix] = 1;
					}
				}
			}
		}
		else if (strpos($header, 'X-Powered-By: PHP/') === 0)
		{
			$php_version = substr($header, 18);
?>
	<dt>PHP Version</dt>
	<dd class="<?php echo result_to_css_class(rate_php_version($php_version)); ?>"><?php echo htmlspecialchars($php_version); ?></dd>
<?php
		}
		else if (strpos($header, 'Server: ') === 0)
		{
			$httpd_version = substr($header, 8);
?>
	<dt>HTTPd Version</dt>
	<dd><?php echo htmlspecialchars($httpd_version); ?></dd>
<?php
		}
	}

	arsort($prefixes, SORT_NUMERIC);

	$phpbb = true;

	if (empty($prefixes))
	{
		$phpbb = false;
		$debug = true;
	}

	if ($phpbb)
	{
?>
	<dt>Discovered cookie names (weight)</dt>
<?php
		foreach ($prefixes as $prefix => $weight)
		{
?>
	<dd><pre class="<?php echo ($weight == 3) ? 'good' : 'bad'; ?>"><?php echo htmlspecialchars($prefix); ?> (<?php echo $weight; ?>)</pre></dd>
<?php
		}

		reset($prefixes);

		if (sizeof($prefixes) > 1)
		{
			$prefix = key($prefixes);
?>
	</dl>
	<p>
		Your board is sending multiple cookies that appear to be phpBB3 cookies, assuming
		<q><var><?php echo $prefix; ?></var></q> is the correct one.
	</p>
	<dl>
<?php
		}
		else
		{
			$prefix = key($prefixes);
		}

		foreach ($cookies as $k => $cookie)
		{
			if (strpos($cookie->name, $prefix) !== 0)
			{
				unset($cookies[$k]);
			}
		}
		$cookies = array_values($cookies); // Reindex

		$domain = $cookies[0]->settableForDomain($url['host']);
		$path = $cookies[0]->settableForPath($url['path']);
?>
	<dt>Cookie name</dt>
	<dd class=""><code><?php echo htmlspecialchars($prefix); ?></code></dd>

	<dt>Cookie domain</dt>
	<dd class="<?php echo result_to_css_class($domain); ?>"><code><?php echo ($cookies[0]->domain ? htmlspecialchars($cookies[0]->domain) : '(unset)'); ?></code></dd>

	<dt>Cookie path</dt>
	<dd class="<?php echo result_to_css_class($path); ?>"><code><?php echo ($cookies[0]->path ? htmlspecialchars($cookies[0]->path) : '(unset)'); ?></code></dd>

	<dt>Cookie secure</dt>
	<dd class="<?php echo ($cookie->secure && $url['scheme'] == 'http' ? 'bad' : (!$cookie->secure && $url['scheme'] == 'https' ? 'notice' : 'good')); ?>"><?php echo ($cookie->secure) ? 'Yes' : 'No'; ?></dd>

</dl>
<?php
	}
	else
	{
?>
</dl>
<p class="bad"><strong>Error:</strong> No phpBB was detected at the specified URL</p>
<?php
	}
?>
<?php
	if ($debug)
	{
?>
<h2>Debug</h2>
<h3>Response</h3>
<h4>Headers</h4>
<ul>
<?php
		foreach ($response['headers'] as $header)
		{
?>
	<li><code><?php echo htmlspecialchars($header); ?></code></li>
<?php
		}
?>
</ul>
<h4>Body</h4>
<pre><?php echo htmlspecialchars($response['body']); ?></pre>
<h3>Statistics</h3>
<dl>
<?php
		foreach ($info as $name => $value)
		{
?>
		<dt><?php echo htmlspecialchars($name); ?></dt>
		<dd><code><?php echo htmlspecialchars($value); ?></code></dd>
<?php
		}
?>
</dl>
<?php
	}
}
else
{
?>
<form method="get">
<fieldset>
	<legend><label for="url">URL</label></legend>
	<p><input id="url" type="text" name="url" value="" style="width: 90%"></p>
</fieldset>
<p><input type="submit" value="Test"></p>
</form>
<?php
}
?>
<hr>
<p><code>$Id: cookie.php 137 2010-03-13 22:25:21Z chris $</code></p>
</body>
</html>
