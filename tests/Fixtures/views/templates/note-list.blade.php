<!DOCTYPE html>
<html lang="en">
<body>
    @foreach (lynx()->pages->find('template=note, sort=name') as $note)
        <p>{{ $note->title }}</p>
    @endforeach
</body>
</html>
