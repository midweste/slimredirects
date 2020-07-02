# SlimRedirects

PSR7 Based Redirect library. Supports wildcard, regex, http to https redirection. Query string handling

-Host redirection support
-Trailing slash is ignored for request matching
-Assumes http://localhost is http://localhost/
-Querystring
--Defaults to combination of request and rule with rule overridding request where matching
--Rule fragment overrides request fragment
--Rule user info overrides request user info

TODO

-"/wild/card/\*/?old=querystring" match only when qs present
