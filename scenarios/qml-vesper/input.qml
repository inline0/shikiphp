import QtQuick 2.15
import QtQuick.Controls 2.15

ApplicationWindow {
    id: root
    width: 400
    height: 300
    visible: true

    property int counter: 0

    Button {
        text: "Clicked " + counter + " times"
        anchors.centerIn: parent
        onClicked: {
            root.counter += 1
            console.log("count:", root.counter)
        }
    }
}
