<p class="hello">@string('greeting', ['default' => 'Hello :name, you have :count rooms', 'pt' => 'Olá :name, tem :count salas'], replace: ['name' => $name, 'count' => count([1, 2])])</p>
<p class="shout">@string('shout', 'Hi :NAME and :Name at 10:00', replace: ['name' => $name])</p>
