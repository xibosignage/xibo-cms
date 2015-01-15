<p>Merci de vous référer aux notes ci-dessous lors de vos réflexions ou contributions au projet Xibo
</p>
<table id="toc" class="toc"><tr><td><div id="toctitle"><h2>Contents</h2></div>
<ul>
<li class="toclevel-1 tocsection-1"><a href="#Introduction"><span class="tocnumber">1</span> <span class="toctext">Introduction</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Comment_contribuer"><span class="tocnumber">2</span> <span class="toctext">Comment contribuer</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Licence"><span class="tocnumber">3</span> <span class="toctext">Licence</span></a></li>
<li class="toclevel-1 tocsection-4"><a href="#Questions_.2F_R.C3.A9ponses"><span class="tocnumber">4</span> <span class="toctext">Questions / Réponses</span></a></li>
<li class="toclevel-1 tocsection-5"><a href="#Report_des_bugs"><span class="tocnumber">5</span> <span class="toctext">Report des bugs</span></a></li>
<li class="toclevel-1 tocsection-6"><a href="#Sugg.C3.A9rer_des_am.C3.A9liorations"><span class="tocnumber">6</span> <span class="toctext">Suggérer des améliorations</span></a></li>
<li class="toclevel-1 tocsection-7"><a href="#Traduire_Xibo"><span class="tocnumber">7</span> <span class="toctext">Traduire Xibo</span></a></li>
<li class="toclevel-1 tocsection-8"><a href="#Triage_des_bugs"><span class="tocnumber">8</span> <span class="toctext">Triage des bugs</span></a></li>
<li class="toclevel-1 tocsection-9"><a href="#Test_des_versions_instables"><span class="tocnumber">9</span> <span class="toctext">Test des versions instables</span></a></li>
<li class="toclevel-1 tocsection-10"><a href="#Documentation"><span class="tocnumber">10</span> <span class="toctext">Documentation</span></a></li>
<li class="toclevel-1 tocsection-11"><a href="#Correction_de_Bugs_.2F_Impl.C3.A9mentation_Blueprints"><span class="tocnumber">11</span> <span class="toctext">Correction de Bugs / Implémentation Blueprints</span></a>
<ul>
<li class="toclevel-2 tocsection-12"><a href="#Vue_d.27ensemble_du_d.C3.A9veloppement"><span class="tocnumber">11.1</span> <span class="toctext">Vue d'ensemble du développement</span></a></li>
<li class="toclevel-2 tocsection-13"><a href="#Liens_importants"><span class="tocnumber">11.2</span> <span class="toctext">Liens importants</span></a></li>
<li class="toclevel-2 tocsection-14"><a href="#Processus_de_d.C3.A9veloppement_.28comment_contribuer.29"><span class="tocnumber">11.3</span> <span class="toctext">Processus de développement (comment contribuer)</span></a>
<ul>
<li class="toclevel-3 tocsection-15"><a href="#Blueprints_.28nouvelles_fonctionnalit.C3.A9s.29"><span class="tocnumber">11.3.1</span> <span class="toctext">Blueprints (nouvelles fonctionnalités)</span></a></li>
<li class="toclevel-3 tocsection-16"><a href="#Bugs"><span class="tocnumber">11.3.2</span> <span class="toctext">Bugs</span></a></li>
</ul>
</li>
</ul>
</li>
<li class="toclevel-1 tocsection-17"><a href="#Traduction"><span class="tocnumber">12</span> <span class="toctext">Traduction</span></a></li>
<li class="toclevel-1 tocsection-18"><a href="#Guide_pour_le_code_source"><span class="tocnumber">13</span> <span class="toctext">Guide pour le code source</span></a>
<ul>
<li class="toclevel-2 tocsection-19"><a href="#Serveur"><span class="tocnumber">13.1</span> <span class="toctext">Serveur</span></a></li>
<li class="toclevel-2 tocsection-20"><a href="#Notes"><span class="tocnumber">13.2</span> <span class="toctext">Notes</span></a></li>
</ul>
</li>
</ul>
</td></tr></table>
<h2> <span class="mw-headline" id="Introduction">Introduction</span></h2>
<p>Xibo est principalement développé par Daniel Garner et Alex Harrington. James Packer s'est aussi beaucoup impliqué dans Xibo 1.0.0. Étant une petite équipe de développement, nous sommes très intime avec le code et le design de Xibo.
</p><p>Nous sommes toujours ravis d'accepter des contributions d'autres personnes et nous encourageons cela.
</p>
<h2> <span class="mw-headline" id="Comment_contribuer">Comment contribuer</span></h2>
<p>Il existe de nombreuses façons de contribuer au projet Xibo, et il ya des tâches que chacun peut faire, même si vous n'êtes pas développeurs&#160;! 
Voici les principaux moyens, énumérés dans l'ordre croissant de difficulté&#160;:
</p>
<ul><li>Poser des questions sur Launchpad Answers&#160;: <a rel="nofollow" class="external free" href="https://answers.launchpad.net/xibo">https://answers.launchpad.net/xibo</a>
</li><li>Reporter un bug que vous avez trouvé sur Launchpad Bugs&#160;: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a> (Le nouvel assistant de report de bugs dans 1.0.0 simplifie grandement cela)
</li><li>Suggérer des améliorations ou nouvelles fonctionnalités sur Launchpad Blueprints&#160;: <a rel="nofollow" class="external free" href="https://blueprints.launchpad.net/xibo">https://blueprints.launchpad.net/xibo</a>
</li><li>Traduire Xibo dans d'autres Langues: https&#160;://translations.launchpad.net/xibo
</li><li>Aider au trie des bugs sur Launchpad Bugs&#160;: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a>
</li><li>Tester les versions de développement instables et reporter les bugs
</li><li>Améliorer la documentation sur le wiki ou le manuel&#160;: <a rel="nofollow" class="external free" href="http://www.xibo.org.uk/manual">http://www.xibo.org.uk/manual</a>
</li><li>Corriger des bugs dans Launchpad Bugs&#160;: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a>
</li><li>Mettre en oeuvre les idées de Xibo Blueprints (nouvelles fonctionnalités) <a rel="nofollow" class="external free" href="https://blueprints.launchpad.net/xibo">https://blueprints.launchpad.net/xibo</a>
</li></ul>
<p>Chacun de ces aspects est traité plus en détail ci-dessous&#160;:
</p>
<h2> <span class="mw-headline" id="Licence">Licence</span></h2>
<p>Xibo est publié sous licence AGPL v3. La documentation de Xibo est sous Creative Commons Attribution ShareAlike License. 
Les traductions de Xibo sont par le biais de Launchpad sous licence BSD (révisé, sans clause de publicité).
</p><p>Toutes les contributions à Xibo doivent être dans le cadre de la licence mentionnées ci-dessus. Si vous vous opposez à contribuer du code sous la licence appropriée, merci d'envoter un courriel à info@xibo.org.uk avant de commencer à travailler, car il est fort possible que votre contribution soit rejetée.
</p>
<h2> <span class="mw-headline" id="Questions_.2F_R.C3.A9ponses">Questions / Réponses</span></h2>
<p>Xibo n'est pas particulièrement bien documenté à l'heure actuelle. Il existe un manuel en cours de rédaction, mais il n'est pas complet. Nous passons beaucoup de temps  à répondre à des requêtes de gens demandant comment installer Xibo, comment utiliser Xibo ou encore de demander si les différents comportements sont des bugs ou pas.
</p><p>Si vous avez utilisé Xibo un peu de temps, et que vous êtes familiarisé avec son fonctionnement, vous êtes plus que qualifié pour suggérer des réponses à ces questions. Tout ce que nous demandons, c'est d'être toujours poli.
</p><p>Nous sommes particulièrement intéressés par des gens qui parlent plus d'une langue et qui se feront un plaisir de répondre à des questions posées dans les langues non-anglophones (comme Alex et Dan ont très peu de compétences dans ce domaine&#160;!) Ou qui peuvent les traduires pour nous&#160;!
</p>
<h2> <span class="mw-headline" id="Report_des_bugs">Report des bugs</span></h2>
<p>Vous pensez que vous avez trouvé un bug&#160;? Vérifiez d'abord dans Launchpad Bugs si quelqu'un a déjà signalé le même problème. Si c'est le cas, vous pouvez vous abonner à l'erreur afin que vous obteniez une notification lorsque son statut change, ou quand une nouvelle version de Xibo est disponible et qui résout le problème.
</p><p>Si vous avez trouvé un nouveau bug, merci de nous le faire savoir&#160;!
</p><p>Xibo 1.0.0 voit la publication des rapports de bugs à partir de l'assistant pour Xibo Server. Au début, merci de suivre les étapes de l'assistant de bug (qui se trouve dans le menu de gestion). Inclure également des captures d'écran le cas échéant, afin de nous montrer ce qui ne va pas. L'assistant de bug rassemble une grande partie des informations communes que nous avons besoin, mais nous pourrions revenir vers vous et vous demander plus d'information ou d'autres exemples afin que nous puissions comprendre ce qui s'est mal passé.
</p>
<h2> <span class="mw-headline" id="Sugg.C3.A9rer_des_am.C3.A9liorations">Suggérer des améliorations</span></h2>
<p>Tout le monde a des idées sur la façon dont Xibo pourrait s'améliorer et non pas seulement les développeurs. Si vous avez une idée, d'abord jeter un oeil dans le Blueprint de Xibo pour voir si elle n'a pas déjà été suggérée. Si c'est le cas, vous pouvez y souscrire pour être notifié si son statut change. N'hésitez pas à ajouter au tableau blanc spécifique, des idées sur la façon dont les choses pourraient fonctionner.
</p><p>Si votre idée est nouvelle, sentez vous libre de créer une nouvelle idée dans Blueprint. Vous devez décrire l'idée que vous avez, en détaillant autant que possible. Si vous avez effectué avant, certains travaux de développement sur Xibo, vous pouvez écrire un nouveau chapitre dans le Wiki pour plus de détails sur la manière dont vous pensez que l'idée pourrait être mise en œuvre.
</p><p>Avec de nouvelles idées, surtout si elles sont radicalement différentes de la direction générale de Xibo développement, il est préférable de commencer par une vue d'ensemble des fonctionnalités, que vous ou un développeur peut préciser pour une date ultérieure.
</p><p>Un aparté: les idées sur Blueprints ne sont que des plans pour l'avenir. Tout ce qui est suggéré ne finira pas comme une caractéristique, surtout si votre idée est applicable à un très petit nombre d'utilisateurs, ou si l'idée est un chemin fondamentallement différent de la direction générale du développement de Xibo.
</p>
<h2> <span class="mw-headline" id="Traduire_Xibo">Traduire Xibo</span></h2>
<p>Xibo est déjà utilisé dans plusieurs pays. Xibo 1.1 et supérieur supporterons la traduction via GNU Gettext. Les traductions seront traités par Launchpad Translations. Si Xibo n'est actuellement pas disponible dans votre langue, ou dans une langue que vous parlez, alors c'est un excellent moyen de nous aider. Launchpad Translations à son propre système, et l'aide sur l'utilisation du système est par l'équipe de Launchpad Translations ets la plus adaptée. Ils ont un guide de démarrage ici: <a rel="nofollow" class="external free" href="https://help.launchpad.net/Translations/StartingToTranslate">https://help.launchpad.net/Translations/StartingToTranslate</a>
</p>
<h2> <span class="mw-headline" id="Triage_des_bugs">Triage des bugs</span></h2>
<p>Le triage est un terme médical, qui signifie catégoriser des problèmes de par leur gravité. Pour Launchpad, le triage est un processus de vérification d'un rapport de bug pour s'assurer qu'il y a toutes les informations qu'auront besoin un développeur pour résoudre le problème, ou de confirmer que le problème existe et qu'il se reproduit.
</p><p>Quand un bug arrive dans Launchpad, son statut est réglé sur "New" et son importance est fixée à "Unknown". Un trieur de bug se penchera sur le rapport de bug, et voir si toutes les informations requises sont présentes, comme par exemple un fichier txt joint à la sortie de l'Assistant de bug du serveur&#160;? Des captures d'écran fournis si nécessaire. Puis le trieur de bug va essayer de reproduire le problème.
</p><p>Si l'information est manquante, merci d'ajouter un commentaire à l'erreur pour demander que les informations soit ajoutées. Chnager le statut du problème à «Incomplete» si vous avez les droits d'accès appropriés.
</p><p>Si toute l'information est là, se lancer à reproduire le problème sur votre système (les machines virtuelles sont vraiment bons pour ça). Si vous pouvez reproduire ce problème, marquer le bug comme "Confirmed", et ajouter un commentaire sur le bug pour décrire comment le reproduire en détail. Joindre les médias nécessaire en cas de besoin ou des liens. 
Si vous ne pouvez pas reproduire le problème, ajoutez un commentaire à cet effet, mais laissez le statut sur "New". Si vous avez confirmé le bug, y'a t'il une solution? Si c'est le cas, la décrire dans un commentaire. Définissez la priorité à "Low" et le statut de "Triaged" si vous avez les droits d'accès appropriés. S'il n'y a pas de problème, la priorité tel que décrit ci-dessous et le statut de "Triaged". Si vous ne savez pas, laissez cette étape, tous ensemble:
</p>
<ul><li> Low - Bugs confirmés avec une solution disponible
</li><li> Middle - Bugs confirmés, sans solution disponible, sans impact significatif sur le fonctionnement de l'ensemble du système
</li><li> High - Bugs confirmés, sans solution disponible, avec impact significatif uniquement sur un ou plusieurs parties de l'ensemble du système, mais qui laisse d'autres parties du système fonctionner.
</li><li> Critical - Bugs confirmés, sans solution disponible et qui rend l'ensemble du système inutilisable.
</li></ul>
<h2> <span class="mw-headline" id="Test_des_versions_instables">Test des versions instables</span></h2>
<p>L'ensemble de la série Xibo 1.1 est "instable". Cela signifie que les versions dans ses séries ne sont pas recommandés pour les environnements de production. Vous pouvez nous aider par l'installation de ces versions et en testant leurs fonctionnement, comme si elle était en environnement réel. Reporter les problèmes dans Launchpad Bugs ou dans Launchpad Answers.
</p>
<h2> <span class="mw-headline" id="Documentation">Documentation</span></h2>
<p>Il y a de grandes sections de la Xibo Manuel qui ne sont pas encore écrites, et les sections doivent être constamment mises à jour en fonction du développement de Xibo. Si vous êtes intéressé pour travailler sur la documentation de Xibo, alors merci d'envoyer un courriel à l'adresse info@xibo.org.uk et nous vous donnerons quelque chose à faire.
</p>
<h2> <span class="mw-headline" id="Correction_de_Bugs_.2F_Impl.C3.A9mentation_Blueprints">Correction de Bugs / Implémentation Blueprints</span></h2>
<h3> <span class="mw-headline" id="Vue_d.27ensemble_du_d.C3.A9veloppement">Vue d'ensemble du développement</span></h3>
<p>Ces notes donnent des directives pour les développeurs qui feront en sorte que tout travail que vous faites ai le maximum de chances de se fusionner dans une version de Xibo.
</p><p>Le projet utilise exclusivement Xibo Launchpad comme plate-forme de développement. Grâce Launchpad nous gérons propositions d'idées (spécifications pour de nouvelles fonctionnalités), les bugs, FAQ (support), les traductions et la gestion du cycle pour le code source. La seule chose que nous ne parvenons pas à Launchpad est la spécification détaillée des documents et de documentation pour les développeurs, ce qui est bien sûr géré ici sur ce Wiki.
</p>
<h3> <span class="mw-headline" id="Liens_importants">Liens importants</span></h3>
<ul><li> Site du projet Xibo&#160;: <a rel="nofollow" class="external free" href="http://www.xibo.org.uk">http://www.xibo.org.uk</a>
</li><li> Page du project sur Launchpad&#160;: <a rel="nofollow" class="external free" href="https://launchpad.net/xibo">https://launchpad.net/xibo</a>
</li><li> Wiki pour les développeurs&#160;: <a rel="nofollow" class="external free" href="http://labs.xibo.org.uk/blueprints">http://labs.xibo.org.uk/blueprints</a> 
</li></ul>
<h3> <span class="mw-headline" id="Processus_de_d.C3.A9veloppement_.28comment_contribuer.29">Processus de développement (comment contribuer)</span></h3>
<p>Je vais diviser le processus de développement en deux sections; Blueprints et bugs. Elles sont essentiellement gérées de la même manière, avec quelques différences mineures.
</p>
<h4> <span class="mw-headline" id="Blueprints_.28nouvelles_fonctionnalit.C3.A9s.29">Blueprints (nouvelles fonctionnalités)</span></h4>
<p>Vous voulez mettre en place une nouvelle fonctionnalité ou une idée pour Xibo&#160;? Ou peut-être que vous voulez un peu modifier la façon dont fonctionne Xibo&#160;? Dans ces deux circonstances, votre première tâche est de créer un entrée dans Blueprint pour cette fonctionnalité et l'attribuer à vous-même. Vous êtes alors responsable de la rédaction d'un document de spécification (cela doit être un bref paragraphe, un article sur ce wiki ou d'une conversation de messagerie instantanée avec nous) dans un second temps, nous pouvons approuver votre idée, demander plus de détails ou suggérer une alternative.
</p><p>Une fois que votre idée est approuvée, vous pouvez demander à un «mentor» de vous aider à démarrer ou vous pouvez récupérer le code de la  "future" version de la série Xibo et commencer le développement. Tous les efforts devraient être faits en utilisant BZR (Bazaar, soutenu par Launchpad) et renvoyer les modifications de votre branche sur Launchpad, dans l'espace réservé au projet Xibo. Il ya de bons tutoriels Launchpad disponibles sur leur site d'aide.
</p><p><b>Note</b>&#160;: Les propositions sur Blueprint seront presque toujours pour les «futures versions". Nous vous suggérons de nommer vos branches "lp:~nom-utilisateur/xibo/titre-blueprint"
</p>
<h4> <span class="mw-headline" id="Bugs">Bugs</span></h4>
<p>Vous avez trouvé un bug et vous voulez nous aider à corriger&#160;? Le premier endroit où aller est la partie "bugs" du projet Xibo dans le Launchpad pour créer un rapport de bug pour le problème que vous avez trouvé. Ce rapport de bug sera ensuite triés par la communautée afin de déterminer si il a déjà été fixé ou si c'est un nouveau bug.
</p><p>Si le bug est confirmé et vous souhaitez appliquer un correctif pour le bug, joindre un commentaire à l'erreur pour le dire, nous donnerons un objectif pour une version et vous l'assignerons. Vous pouvez ensuite récupérer le code de la branche correspondante à la version Xibo spécifiée lros de l'attribution du bug (contrairement à une nouvelle fonctionnalité, un bug est assignée pour une version pour la version stable ou une future version).
</p><p>Une fois que vous avez récupérer le code, mettre en œuvre la correction du bug et renvoyer le code sur le Launchpad. Nous vous suggérons de nommer votre branche "lp:~nom-utilisateur/xibo/bug#"
</p><p><i>Check list</i>
</p>
<pre>1. Enregistrer un rapport de bug ou une nouvelle fonctionnalité Blueprint in Launchpad
2. Pour une fonctionnalité Blueprint, créer une spécifiation détaillée, pour un bug aider au triage de celui-ci
3. Obtenez l'approbation de votre fonctionnalité Blueprint / confirmation de votre bug et vous l'assignez
4. Récupérer le code la branche correspondante à la fonctionnalité Blueprint / de votre bug
5. Implémenter la fonctionnalité Blueprint / corriger le bug et publié le code dans une nouvelle branche sur Launchpad
6. Est-ce que le code correspond au style du code Xibo indiqué dans le guide 
7. Est-ce que le code utilise corectement les libraries d'objets Xibo (Config, Kit, ResponseManager, DB, etc)
8. Es-ce que le code affiche bien les variables (utilisation des librairies de fonctions)
9. Faire une demande de fusion du code
10. Attendre que votre code soit relu et fusionné&#160;! 
11. Les points à 1-3 peuvent mettre du temps à être complété, mais cela en vaut la peine&#160;!
</pre>
<h2> <span class="mw-headline" id="Traduction">Traduction</span></h2>
<p>Nous sommes actuellement dans le processus d'ajout de traduction à Xibo. Il y aura une librairie de méthode pour permettre la traduction à la place des appels Gettext en PHP _( "string"). Tous les nouveaux code doivent utiliser cette fonction. Aussi, merci de jeter un oeil à la bibliothèque existante de variables et de voir si le message que vous avez besoin pour l'affichage est déjà dans la librairie de traduction. Si il y a une chaîne de caractères qui existe déjà, merci de la réutiliser plutôt que de créer une nouvelle, très similaire, qui doit être traduit séparément.
</p>
<h2> <span class="mw-headline" id="Guide_pour_le_code_source">Guide pour le code source</span></h2>
<h4> <span class="mw-headline" id="Serveur">Serveur</span></h4>
<p>Les blocs sont exprimés comme suit:
</p>
<pre>class Example
{
   function registerDisplay(var)
   {
       print "OK";
   }
}

$example = new Example();
</pre>
<p>Les commentaires sont utiles. Merci d'ajouter des commentaires adéquats pour décrire votre code.
Si vous incluez le code écrit par quelqu'un d'autre, merci de documenter complétement dans ce cas là où vous l'avez obtenu le code, qui a écrit et sous quelle licence vous l'utiliser. <i> 'Gardez à l'esprit que la licence doit être compatible AGPLv3 ou suivante.'</i>
</p><p>Xibo est très orienté objet. Merci de vous assurer que votre code suive aussi le style orienté objet, s'il ya lieu.
</p>
<h3> <span class="mw-headline" id="Notes">Notes</span></h3>
<p>En raison de contraintes de temps d'Alex et moi-même nous ne pouvons accepter tout changement de code qui entrent dans les catégories ci-dessous:
</p>
<ul><li> Ne pas avoir une fonctionnalité Blueprint ou bug attribué
</li><li> Avoir plus d'une fonctionnalité Blueprint ou bug en même temps (sans accord préalable)
</li><li> Ne pas avoir une licence appropriée (voir ci-dessus)
</li></ul>
<p>Cette cela peut sembler excessivement complexe de contribuer à Xibo et si cela est trop pour vous, nous vous comprenons. Toutefois, cette méthode donne au projet Xibo le maximum de chances d'être stable, extensible et bien géré&#160;!
</p><p>Nous ne voulons pas retarder la publication d'importantes corrections et améliorations, pas plus que vous voulez attendre leur libération&#160;! C'est pourquoi nous avons adopté la solution Launchpad. Lorsqu'il est utilisé à bon escient, il permet de corriger les bugs et fusionné ensuite avec une version, et les caractéristiques d'être mis en oeuvre de façon isolée. Il permettra également de réduire les chances que deux contributions entre en conflit les unes avec les autres.
</p><p>Il s'agit également de la méthode que les Mainteneurs Xibo (Alex et moi) élabore pour le développement et la correction des bugs dans Xibo!
</p><p>Nous attendons vos contributions!
</p>