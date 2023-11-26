import "./styles/App.css";
import Login from "./pages/auth/Login";

function App() {
  const employes = [
    {
      id: 1,
      nom: "Mohamed",
      prenom: "Karim",
      age: 35,
      fonction: "developpeur mobile",
    },
    {
      id: 2,
      nom: "Nedra",
      prenom: "Ben Mohamed",
      age: 32,
      fonction: "developpeur web",
    },
  ];

  return (
    <>
      <div>formation</div>
      <Login employes={employes} />
    </>
  );
}

export default App;