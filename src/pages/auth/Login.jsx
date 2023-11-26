import React from "react";

export default function Login(props) {
  console.log(props);
  const { employes } = props;

  return (
    <div style={{ padding: "10px", backgroundColor: "#bbb" }}>
      {/* <div>{JSON.stringify(employes)}</div> */}

      {employes.map((item) => (
        <div key={item.id} style={{ marginBottom: "10px" }}>
          <div>ID:{item.id}</div>
          <div>Nom:{item.nom}</div>
          <div>Prenom:{item.prenom}</div>
          <div>Age:{item.age}</div>
          <div>Fonction:{item.fonction}</div>
        </div>
      ))}
    </div>
  );
}