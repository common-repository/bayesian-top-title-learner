=== Bayesian top title learner ===
Contributors: gversteeg, mdawaffe
Donate link: http://www.its.caltech.edu/~gregv/projects.html
Tags: posts, automatic, rating, links, widget, sidebar
Requires at least: 2.5
Tested up to: 2.6.3
Stable tag: 1.0

Display links to your most 'interesting' posts, where interestingness is determined automatically by a Bayesian learning algorithm.

== Description ==

Someone has gotten to your page somehow. You want a sidebar with the links they are most likely to click on.

Naive strategy: Put the most clicked posts in the list. Why might this be suboptimal? Maybe you wrote one post about accordions and it got linked from The World Accordion Association Blog (WAAB). It got many clicks. But most visitors to your page have no interest in accordions.

Less naive: Have your sidebar display random links and keep track of which ones get clicked. This is cool, now we are doing experiments to find exactly what we need. But experiments are expensive, we only have a limited amount of eyeballs coming our way. Are we using our data optimally? For instance, if we know A is very interesting, and we know nothing about B, and B gets clicked over A, that should be worth more. So we want to know more than whether B is clicked or not, but what else was clicked in that context.

More sophisticated: How to take advantage of information about what other things were clicked? First, we make a model of browsing behavior. Then we need to figure out the parameters for this model. Bayesian methods allow us to learn the parameters with few assumptions. See Additional Information for more about the mathematical theory.

== Installation ==

1. Upload `bttl.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'Design' then 'Widgets' menu to add it to your sidebar. Change the default options in the Widget menu if you like.

Do make sure the path to bttl.php is /wp-content/plugins/bttl.php with no extra directories in between.

== Frequently Asked Questions ==

= This is your first plugin, isn't it? =

That's not really a question, but it does express an obvious truth.

= How is it possible that someone with so few wordpress skillz managed to make this plugin work, no matter how poorly? =

Only by the grace of mdawaffe answering my silly questions.

= I stood in line two hours on the first day to download your plugin and then it didn't work ? =

Sorry about that. Thanks Ulrik for the heads up and fix.

= Random links show up but when I click on them nothing (good) happens? =

First, make sure that bttl.php is in the right directory /wp-content/plugins/bttl.php 
Second, bttl.php keeps looking into containing directories until it finds wp-load.php , is that there?
If there's still a problem, I'd be interested to hear it.

== Additional information ==

The theory behind the plugin's approach is provided in [this document.](http://www.its.caltech.edu/~gregv/bttl_notes.pdf)

Options: Reset does the obvious and resets the statistics. Title changes the title in your sidebar. Number says how many to display.
Show scores is the interesting one. It puts a an expectation value for the current interest score next to each item. The last (extra line) is the expectation value for the scenario parameter (that is, the fraction of people who are probably looking at the sidebar). I just added a fun feature: plot. When you click plot it shows you a graph of the probability distribution for what values the parameters can take. Note that it is unnormalized.
At the moment there is no option in the widget pane, but there is a global variable in the php file 'processdelayinsec' which says how often to update statistics. 
