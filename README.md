SynoRoundcube
=============

I have using mail station to fetch my pop3 mailbox start from two years ago.
The pop3 mods made was hard code to the roundcube by syno.

I'm not a php programer but I also tried to transport this feature to official release of roundcube(on 0.9.4, I got mail thread function which not work properly on 0.5.x)
Finally, the feature works, but not perfectly, I noticed some ui bugs and it confuse me a long time.

As DSM was updated to 5.0, the MailStation was updated to 20140220-0159 also.. which is corresponding to roundcube's official verison 0.9.5
Looks a very good thing to me, so I happily upgrade my MailStation to 20140220-0159.
The interface is pretty nice, many features has been enhanced...
In this version, syno rewritten their pop3 mods and made it to a roundcube plugin!!

All features looks wonderful, only but one thing lost:
It will not force fetch pop3 mailbox after I click the refresh button….. looks like syno's guys forgotten it.

It isn't too hard for me.
The bug only takes me two hours to make it normal.
My code added to syno's plugin was a hook, which I think is a clean fix.

http://forum.synology.com/enu/viewtopic.php?f=32&t=83358
