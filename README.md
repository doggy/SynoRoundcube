SynoRoundcube
=============

I have using mail station to fetch my pop3 mailbox two years.
The pop3 feature made by syno was hard code to the round cube.

I'm not a php programer but I also tried to port this feature to 0.9.4 (on 0.9.4, I got mail thread function which not work properly on 0.5.x)
Finally, the feature works, but not perfectly, some ui bugs confuse me all the time.

As DSM was updated to 5.0, the MailStation was updated to 20140220-0159 also.. which corresponding to round cube 0.9.5
It is a very good thing for me.
So I happily upgrade it to 20140220-0159.
It's interface is pretty nice, so many features has been enhanced.
And syno rewritten the pop3 feature and made it to a round cube plugin!!

All features looks wonderful, only one thing lost:
It will not force fetch pop3 mailbox after I click the refresh button….. looks like syno's guys forgotten it.

It isn't too hard for me.
The bug only takes me two hours to make it normal.
The code added to synod's plugin was a hook, which is clean fix.

http://forum.synology.com/enu/viewtopic.php?f=32&t=83358
