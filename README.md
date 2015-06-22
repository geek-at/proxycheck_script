# Simple proxy checkign script

This script was used while reachsearching for [this blog entry](https://blog.haschek.at/post/fd854)

I do not provide a proxy list you have to create it yourself. Just name it ```proxylist_http.txt``` for your HTTP proxies or ```proxylist_socks.txt``` for SOCKS proxies.

Every line must be one ip:port combination like: 192.168.0.5:3128

## What does it scan proxies for?
- Is HTTPS allowed?
- Is JS modified?
- Are static websites modified?
- Will it hide my IP?

## What else does it do
It will create a folder with a random session name and save a copy of all modified data there.
So if a .js file is modified it will save it with the name of the proxy so you can analyze it further.


## Output looks like this
![Sample output](https://www.pictshare.net/store/3053269eb4.png)
