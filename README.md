Opauth-Flickr
=============
Flickr strategy for [Opauth][1], based on Opauth-OAuth & Opauth-Twitter.

Getting started
----------------
1. Install Opauth-Flickr:
   ```bash
   cd path_to_opauth/Strategy
   git clone git://github.com/pocket7878/opauth-flickr.git Flickr
   curl -s http://getcomposer.org/installer | php
   php composer.phar install
   ```

2. Create Flickr application at http://www.flickr.com/services/
	
3. Configure Opauth-Flickr strategy with at least `key` and `secret`.

4. Direct user to `http://path_to_opauth/flickr` to authenticate


Strategy configuration
----------------------

Required parameters:

```php
<?php
'Flickr' => array(
	'key' => 'YOUR APP KEY',
	'secret' => 'YOUR APP SECRET'
)
```

See FlickrStrategy.php for optional parameters.

Dependencies
------------
tmhOAuth requires hash_hmac and cURL.  
hash_hmac is available on PHP 5 >= 5.1.2.

Reference
---------
 - [Flickr Services](http://www.flickr.com/services/api/auth.oauth.html)

License
---------
Opauth-Flickr is MIT Licensed  
Copyright © 2012 Pocket7878 (http://poketo7878.dip.jp)

tmhOAuth is [Apache 2 licensed](https://github.com/pocket7878/opauth-flickr/blob/master/Vendor/tmhOAuth/LICENSE).

[1]: https://github.com/uzyn/opauth
