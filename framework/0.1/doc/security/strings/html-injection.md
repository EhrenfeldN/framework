# HTML Injection

Any variable being printed on a HTML page should use the [html](../../../doc/system/functions.md)() function.

If you don't, then it's possible that the variable could include some malicious HTML, typically JavaScript.

It could also be as simple as loading an image from an untrusted website... so if the victim is running a browser with known image processing vulnerabilities (e.g. IE6), that image could contain code to cause a buffer overflow, or the image could contain [JavaScript code](http://adblockplus.org/blog/the-hazards-of-mime-sniffing), or perform a 301 redirect back to the site for a nice [CRSF](../../../doc/security/csrf.md).

---

## JavaScript

Avoid adding JavaScript code to the HTML, so never do something like:

	$response->head_add_html('<script>var x = ' . json_encode($x) . ';</script>');

Instead use:

	$response->js_code_add('var x = ' . json_encode($x) . ';');

Because the variable could still include a </script> tag, and the HTML parser (not being aware of the rules of JavaScript) will just see that as the end of the JavaScript, and will continue to treat the rest as HTML.
